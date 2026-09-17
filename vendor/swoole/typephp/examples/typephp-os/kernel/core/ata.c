/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | Minimal polling ATA PIO block device for the QEMU primary IDE disk. |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

#include <stddef.h>
#include <stdint.h>
#include <string.h>

enum {
    ATA_DATA = 0x1f0,
    ATA_ERROR = 0x1f1,
    ATA_SECTOR_COUNT = 0x1f2,
    ATA_LBA_LOW = 0x1f3,
    ATA_LBA_MID = 0x1f4,
    ATA_LBA_HIGH = 0x1f5,
    ATA_DRIVE = 0x1f6,
    ATA_STATUS = 0x1f7,
    ATA_COMMAND = 0x1f7,
    ATA_ALT_STATUS = 0x3f6,

    ATA_STATUS_ERROR = 0x01,
    ATA_STATUS_DATA_REQUEST = 0x08,
    ATA_STATUS_DEVICE_FAULT = 0x20,
    ATA_STATUS_BUSY = 0x80,

    ATA_COMMAND_READ_SECTORS = 0x20,
    ATA_COMMAND_WRITE_SECTORS = 0x30,
    ATA_COMMAND_CACHE_FLUSH = 0xe7,
    ATA_SECTOR_SIZE = 512,
    ATA_TIMEOUT = 10000000,
    /* QEMU uses a 32 MiB FAT16 image. One direct-mapped entry per image
     * sector turns the current disk into an effective whole-device cache;
     * larger future disks remain correct and use the same O(1) mapping. */
    ATA_CACHE_CAPACITY = 65536,
    PCI_CONFIG_ADDRESS = 0xcf8,
    PCI_CONFIG_DATA = 0xcfc,
};

static int ata_initialized;
static uint64_t ata_cache_hit_count;
static uint64_t ata_cache_miss_count;

typedef struct {
    uint32_t lba;
    int valid;
    unsigned char data[ATA_SECTOR_SIZE];
} ata_cache_entry;

static ata_cache_entry ata_cache[ATA_CACHE_CAPACITY];

static void ata_delay_400ns(void);

static inline void outb(uint16_t port, uint8_t value)
{
    __asm__ volatile("outb %0, %1" : : "a"(value), "Nd"(port));
}

static inline uint8_t inb(uint16_t port)
{
    uint8_t value;
    __asm__ volatile("inb %1, %0" : "=a"(value) : "Nd"(port));
    return value;
}

static inline void outw(uint16_t port, uint16_t value)
{
    __asm__ volatile("outw %0, %1" : : "a"(value), "Nd"(port));
}

static inline uint16_t inw(uint16_t port)
{
    uint16_t value;
    __asm__ volatile("inw %1, %0" : "=a"(value) : "Nd"(port));
    return value;
}

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

static void ata_controller_init(void)
{
    if (ata_initialized) {
        return;
    }
    /* QEMU's direct Multiboot path does not run a PC firmware that enables
     * the PIIX IDE PCI function. Enable I/O decoding for 00:01.1 while
     * leaving the controller in its reset legacy compatibility mode. */
    outl(PCI_CONFIG_ADDRESS, UINT32_C(0x80000904));
    uint32_t command = inl(PCI_CONFIG_DATA);
    outl(PCI_CONFIG_DATA, command | UINT32_C(0x00000001));
    outb(ATA_ALT_STATUS, 0x00);
    ata_delay_400ns();
    ata_initialized = 1;
}

static void ata_delay_400ns(void)
{
    (void) inb(ATA_ALT_STATUS);
    (void) inb(ATA_ALT_STATUS);
    (void) inb(ATA_ALT_STATUS);
    (void) inb(ATA_ALT_STATUS);
}

static int ata_wait_not_busy(void)
{
    for (uint32_t attempt = 0; attempt < ATA_TIMEOUT; ++attempt) {
        const uint8_t status = inb(ATA_STATUS);
        if (status == 0xff) {
            return 0;
        }
        if ((status & ATA_STATUS_BUSY) == 0) {
            return (status & (ATA_STATUS_ERROR | ATA_STATUS_DEVICE_FAULT)) == 0;
        }
        __asm__ volatile("pause");
    }
    return 0;
}

static int ata_wait_data(void)
{
    for (uint32_t attempt = 0; attempt < ATA_TIMEOUT; ++attempt) {
        const uint8_t status = inb(ATA_STATUS);
        if ((status & (ATA_STATUS_ERROR | ATA_STATUS_DEVICE_FAULT)) != 0) {
            (void) inb(ATA_ERROR);
            return 0;
        }
        if ((status & ATA_STATUS_BUSY) == 0
            && (status & ATA_STATUS_DATA_REQUEST) != 0) {
            return 1;
        }
        __asm__ volatile("pause");
    }
    return 0;
}

static int ata_select_lba(uint32_t lba)
{
    ata_controller_init();
    if (lba >= UINT32_C(0x10000000) || !ata_wait_not_busy()) {
        return 0;
    }
    outb(ATA_DRIVE, (uint8_t) (0xe0u | ((lba >> 24u) & 0x0fu)));
    ata_delay_400ns();
    outb(ATA_SECTOR_COUNT, 1);
    outb(ATA_LBA_LOW, (uint8_t) lba);
    outb(ATA_LBA_MID, (uint8_t) (lba >> 8u));
    outb(ATA_LBA_HIGH, (uint8_t) (lba >> 16u));
    return 1;
}

int typephp_os_disk_available(void)
{
    ata_controller_init();
    const uint8_t status = inb(ATA_STATUS);
    /* QEMU leaves the status register at zero until the first command when
     * booting a Multiboot kernel directly, without SeaBIOS ATA probing. */
    return status != 0xff;
}

static int ata_read_sector_raw(uint32_t lba, unsigned char *data)
{
    if (data == 0 || !ata_select_lba(lba)) {
        return 0;
    }
    outb(ATA_COMMAND, ATA_COMMAND_READ_SECTORS);
    ata_delay_400ns();
    if (!ata_wait_data()) {
        return 0;
    }
    for (size_t offset = 0; offset < ATA_SECTOR_SIZE; offset += 2) {
        const uint16_t word = inw(ATA_DATA);
        data[offset] = (unsigned char) word;
        data[offset + 1] = (unsigned char) (word >> 8u);
    }
    return ata_wait_not_busy();
}

static int ata_write_sector_raw(uint32_t lba, const unsigned char *data)
{
    if (data == 0 || !ata_select_lba(lba)) {
        return 0;
    }
    outb(ATA_COMMAND, ATA_COMMAND_WRITE_SECTORS);
    ata_delay_400ns();
    if (!ata_wait_data()) {
        return 0;
    }
    for (size_t offset = 0; offset < ATA_SECTOR_SIZE; offset += 2) {
        outw(ATA_DATA, (uint16_t) (data[offset] | ((uint16_t) data[offset + 1] << 8u)));
    }
    return ata_wait_not_busy();
}

static ata_cache_entry *ata_cache_slot(uint32_t lba, int *found)
{
    ata_cache_entry *entry = &ata_cache[lba & (ATA_CACHE_CAPACITY - 1u)];
    *found = entry->valid && entry->lba == lba;
    return entry;
}

int typephp_os_disk_read_sector(uint32_t lba, unsigned char *data)
{
    int found;
    ata_cache_entry *entry;
    if (data == 0) {
        return 0;
    }
    entry = ata_cache_slot(lba, &found);
    if (found) {
        ++ata_cache_hit_count;
        memcpy(data, entry->data, ATA_SECTOR_SIZE);
        return 1;
    }
    ++ata_cache_miss_count;
    if (!ata_read_sector_raw(lba, data)) {
        return 0;
    }
    entry->valid = 1;
    entry->lba = lba;
    memcpy(entry->data, data, ATA_SECTOR_SIZE);
    return 1;
}

int typephp_os_disk_write_sector(uint32_t lba, const unsigned char *data)
{
    int found;
    ata_cache_entry *entry;
    if (data == 0 || !ata_write_sector_raw(lba, data)) {
        return 0;
    }
    entry = ata_cache_slot(lba, &found);
    (void) found;
    entry->valid = 1;
    entry->lba = lba;
    memcpy(entry->data, data, ATA_SECTOR_SIZE);
    return 1;
}

unsigned long typephp_os_disk_cache_hits(void)
{
    return (unsigned long) ata_cache_hit_count;
}

unsigned long typephp_os_disk_cache_misses(void)
{
    return (unsigned long) ata_cache_miss_count;
}

unsigned long typephp_os_disk_cache_bytes(void)
{
    return (unsigned long) sizeof(ata_cache);
}

int typephp_os_disk_flush(void)
{
    if (!ata_wait_not_busy()) {
        return 0;
    }
    outb(ATA_COMMAND, ATA_COMMAND_CACHE_FLUSH);
    ata_delay_400ns();
    return ata_wait_not_busy();
}
