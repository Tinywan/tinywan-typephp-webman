/* Intel 82540EM (QEMU e1000) driver using its indirect I/O BAR. */

#include "e1000.h"
#include "../vm.h"

#include <stdint.h>
#include <string.h>

enum {
    E1000_VENDOR = 0x8086,
    E1000_DEVICE = 0x100e,
    PCI_ADDRESS = 0xcf8,
    PCI_DATA = 0xcfc,
    PCI_COMMAND = 0x04,
    PCI_BAR_BEGIN = 0x10,
    PCI_BAR_END = 0x28,
    PCI_COMMAND_MEMORY = 2,
    PCI_COMMAND_BUS_MASTER = 4,
    REG_CTRL = 0x0000,
    REG_STATUS = 0x0008,
    REG_EERD = 0x0014,
    REG_ICR = 0x00c0,
    REG_IMC = 0x00d8,
    REG_RCTL = 0x0100,
    REG_TCTL = 0x0400,
    REG_TIPG = 0x0410,
    REG_RDBAL = 0x2800,
    REG_RDBAH = 0x2804,
    REG_RDLEN = 0x2808,
    REG_RDH = 0x2810,
    REG_RDT = 0x2818,
    REG_TDBAL = 0x3800,
    REG_TDBAH = 0x3804,
    REG_TDLEN = 0x3808,
    REG_TDH = 0x3810,
    REG_TDT = 0x3818,
    REG_RAL = 0x5400,
    REG_RAH = 0x5404,
    CTRL_RST = 1u << 26,
    CTRL_SLU = 1u << 6,
    EERD_START = 1,
    EERD_DONE = 1u << 4,
    RCTL_EN = 1u << 1,
    RCTL_BAM = 1u << 15,
    RCTL_SECRC = 1u << 26,
    TCTL_EN = 1u << 1,
    TCTL_PSP = 1u << 3,
    RX_DESCRIPTOR_DONE = 1,
    TX_DESCRIPTOR_DONE = 1,
    TX_COMMAND_EOP = 1,
    TX_COMMAND_IFCS = 1u << 1,
    TX_COMMAND_RS = 1u << 3,
    DESCRIPTOR_COUNT = 256,
    BUFFER_SIZE = 2048,
};

typedef struct __attribute__((packed)) {
    uint64_t address;
    uint16_t length;
    uint16_t checksum;
    uint8_t status;
    uint8_t errors;
    uint16_t special;
} rx_descriptor;

typedef struct __attribute__((packed)) {
    uint64_t address;
    uint16_t length;
    uint8_t checksum_offset;
    uint8_t command;
    uint8_t status;
    uint8_t checksum_start;
    uint16_t special;
} tx_descriptor;

static rx_descriptor rx_descriptors[DESCRIPTOR_COUNT]
    __attribute__((aligned(4096)));
static tx_descriptor tx_descriptors[DESCRIPTOR_COUNT]
    __attribute__((aligned(4096)));
static uint8_t rx_buffers[DESCRIPTOR_COUNT][BUFFER_SIZE]
    __attribute__((aligned(4096)));
static uint8_t tx_buffers[DESCRIPTOR_COUNT][BUFFER_SIZE]
    __attribute__((aligned(4096)));
static uint8_t mac_address[6];
static volatile uint8_t *mmio_base;
static uint16_t rx_index;
static uint16_t tx_index;
static int initialized;

static inline void outl(uint16_t port, uint32_t value)
{
    __asm__ volatile("outl %0, %1" : : "a"(value), "Nd"(port));
}

static inline uint32_t inl(uint16_t port)
{
    uint32_t value;
    __asm__ volatile("inl %1, %0" : "=a"(value) : "Nd"(port));
    return value;
}

static uint32_t pci_read32(
    uint8_t bus, uint8_t device, uint8_t function, uint8_t offset)
{
    const uint32_t address = UINT32_C(0x80000000)
        | ((uint32_t) bus << 16u) | ((uint32_t) device << 11u)
        | ((uint32_t) function << 8u) | (offset & 0xfcu);
    outl(PCI_ADDRESS, address);
    return inl(PCI_DATA);
}

static void pci_write32(uint8_t bus, uint8_t device, uint8_t function,
    uint8_t offset, uint32_t value)
{
    const uint32_t address = UINT32_C(0x80000000)
        | ((uint32_t) bus << 16u) | ((uint32_t) device << 11u)
        | ((uint32_t) function << 8u) | (offset & 0xfcu);
    outl(PCI_ADDRESS, address);
    outl(PCI_DATA, value);
}

static uint32_t register_read(uint32_t offset)
{
    return *(volatile uint32_t *) (mmio_base + offset);
}

static void register_write(uint32_t offset, uint32_t value)
{
    *(volatile uint32_t *) (mmio_base + offset) = value;
}

static int read_eeprom_word(uint8_t address, uint16_t *value)
{
    register_write(REG_EERD, ((uint32_t) address << 8u) | EERD_START);
    for (unsigned int attempt = 0; attempt < 1000000u; ++attempt) {
        const uint32_t result = register_read(REG_EERD);
        if ((result & EERD_DONE) != 0) {
            *value = (uint16_t) (result >> 16u);
            return 1;
        }
        __asm__ volatile("pause");
    }
    return 0;
}

static int find_device(uint8_t *found_bus, uint8_t *found_device,
    uint8_t *found_function)
{
    for (uint16_t bus = 0; bus < 256; ++bus) {
        for (uint8_t device = 0; device < 32; ++device) {
            for (uint8_t function = 0; function < 8; ++function) {
                const uint32_t identity = pci_read32(
                    (uint8_t) bus, device, function, 0);
                if ((identity & 0xffffu) == E1000_VENDOR
                    && (identity >> 16u) == E1000_DEVICE) {
                    *found_bus = (uint8_t) bus;
                    *found_device = device;
                    *found_function = function;
                    return 1;
                }
                if (function == 0
                    && (pci_read32((uint8_t) bus, device, 0, 0x0c)
                        & UINT32_C(0x00800000)) == 0) {
                    break;
                }
            }
        }
    }
    return 0;
}

int typephp_e1000_init(void)
{
    uint8_t bus;
    uint8_t device;
    uint8_t function;
    if (!find_device(&bus, &device, &function)) {
        return 0;
    }
    uint64_t memory_base = 0;
    for (uint8_t offset = PCI_BAR_BEGIN; offset < PCI_BAR_END; offset += 4) {
        const uint32_t bar = pci_read32(bus, device, function, offset);
        if (bar != 0 && bar != UINT32_MAX && (bar & 1u) == 0) {
            memory_base = bar & UINT32_C(0xfffffff0);
            if ((bar & 6u) == 4u && offset + 4 < PCI_BAR_END) {
                memory_base |= (uint64_t) pci_read32(
                    bus, device, function, (uint8_t) (offset + 4)) << 32u;
            }
            break;
        }
    }
    if (memory_base == 0) {
        return 0;
    }
    mmio_base = (volatile uint8_t *) typephp_vm_map_mmio(
        memory_base, UINT64_C(128) * 1024u);
    if (mmio_base == 0) {
        return 0;
    }
    uint32_t command = pci_read32(bus, device, function, PCI_COMMAND);
    pci_write32(bus, device, function, PCI_COMMAND,
        command | PCI_COMMAND_MEMORY | PCI_COMMAND_BUS_MASTER);

    register_write(REG_IMC, UINT32_MAX);
    register_write(REG_CTRL, register_read(REG_CTRL) | CTRL_RST);
    for (volatile unsigned int delay = 0; delay < 1000000u; ++delay) {
        __asm__ volatile("pause");
    }
    register_write(REG_IMC, UINT32_MAX);
    (void) register_read(REG_ICR);
    register_write(REG_CTRL, register_read(REG_CTRL) | CTRL_SLU);

    uint32_t ral = register_read(REG_RAL);
    uint32_t rah = register_read(REG_RAH);
    if ((rah & UINT32_C(0x80000000)) != 0) {
        mac_address[0] = (uint8_t) ral;
        mac_address[1] = (uint8_t) (ral >> 8u);
        mac_address[2] = (uint8_t) (ral >> 16u);
        mac_address[3] = (uint8_t) (ral >> 24u);
        mac_address[4] = (uint8_t) rah;
        mac_address[5] = (uint8_t) (rah >> 8u);
    } else {
        uint16_t word[3];
        if (!read_eeprom_word(0, &word[0])
            || !read_eeprom_word(1, &word[1])
            || !read_eeprom_word(2, &word[2])) {
            return 0;
        }
        for (unsigned int index = 0; index < 3; ++index) {
            mac_address[index * 2] = (uint8_t) word[index];
            mac_address[index * 2 + 1] = (uint8_t) (word[index] >> 8u);
        }
        ral = (uint32_t) mac_address[0]
            | ((uint32_t) mac_address[1] << 8u)
            | ((uint32_t) mac_address[2] << 16u)
            | ((uint32_t) mac_address[3] << 24u);
        rah = (uint32_t) mac_address[4]
            | ((uint32_t) mac_address[5] << 8u)
            | UINT32_C(0x80000000);
        register_write(REG_RAL, ral);
        register_write(REG_RAH, rah);
    }

    memset(rx_descriptors, 0, sizeof(rx_descriptors));
    memset(tx_descriptors, 0, sizeof(tx_descriptors));
    for (unsigned int index = 0; index < DESCRIPTOR_COUNT; ++index) {
        rx_descriptors[index].address =
            (uint64_t) (uintptr_t) rx_buffers[index];
        tx_descriptors[index].address =
            (uint64_t) (uintptr_t) tx_buffers[index];
        tx_descriptors[index].status = TX_DESCRIPTOR_DONE;
    }
    rx_index = 0;
    tx_index = 0;

    const uint64_t rx_address = (uint64_t) (uintptr_t) rx_descriptors;
    register_write(REG_RDBAL, (uint32_t) rx_address);
    register_write(REG_RDBAH, (uint32_t) (rx_address >> 32u));
    register_write(REG_RDLEN, sizeof(rx_descriptors));
    register_write(REG_RDH, 0);
    register_write(REG_RDT, DESCRIPTOR_COUNT - 1u);
    register_write(REG_RCTL, RCTL_EN | RCTL_BAM | RCTL_SECRC);

    const uint64_t tx_address = (uint64_t) (uintptr_t) tx_descriptors;
    register_write(REG_TDBAL, (uint32_t) tx_address);
    register_write(REG_TDBAH, (uint32_t) (tx_address >> 32u));
    register_write(REG_TDLEN, sizeof(tx_descriptors));
    register_write(REG_TDH, 0);
    register_write(REG_TDT, 0);
    register_write(REG_TCTL,
        TCTL_EN | TCTL_PSP | (0x10u << 4u) | (0x40u << 12u));
    register_write(REG_TIPG, UINT32_C(0x0060200a));
    initialized = (register_read(REG_STATUS) & 2u) != 0;
    return initialized;
}

const uint8_t *typephp_e1000_mac(void)
{
    return mac_address;
}

int typephp_e1000_transmit(const void *data, size_t length)
{
    if (!initialized || data == 0 || length == 0 || length > BUFFER_SIZE) {
        return 0;
    }
    tx_descriptor *descriptor = &tx_descriptors[tx_index];
    unsigned int attempts = 0;
    while ((descriptor->status & TX_DESCRIPTOR_DONE) == 0) {
        if (++attempts == 10000000u) {
            return 0;
        }
        __asm__ volatile("pause");
    }
    memcpy(tx_buffers[tx_index], data, length);
    descriptor->length = (uint16_t) length;
    descriptor->command = TX_COMMAND_EOP | TX_COMMAND_IFCS | TX_COMMAND_RS;
    descriptor->status = 0;
    __asm__ volatile("mfence" : : : "memory");
    tx_index = (uint16_t) ((tx_index + 1u) % DESCRIPTOR_COUNT);
    register_write(REG_TDT, tx_index);
    return 1;
}

int typephp_e1000_receive(void *data, size_t capacity, size_t *length)
{
    rx_descriptor *descriptor;
    if (!initialized || data == 0 || length == 0) {
        return 0;
    }
    descriptor = &rx_descriptors[rx_index];
    if ((descriptor->status & RX_DESCRIPTOR_DONE) == 0) {
        return 0;
    }
    *length = descriptor->length;
    if (descriptor->errors != 0 || *length == 0 || *length > capacity) {
        *length = 0;
    } else {
        memcpy(data, rx_buffers[rx_index], *length);
    }
    descriptor->status = 0;
    __asm__ volatile("mfence" : : : "memory");
    register_write(REG_RDT, rx_index);
    rx_index = (uint16_t) ((rx_index + 1u) % DESCRIPTOR_COUNT);
    return *length != 0;
}
