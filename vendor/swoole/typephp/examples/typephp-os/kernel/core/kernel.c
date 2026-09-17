typedef unsigned char uint8_t;
typedef unsigned short uint16_t;
typedef unsigned int uint32_t;
typedef unsigned long uint64_t;

enum {
    MULTIBOOT_BOOTLOADER_MAGIC = 0x2badb002u,
    VGA_WIDTH = 80,
    VGA_HEIGHT = 25,
    COM1 = 0x3f8,
    SERIAL_BUFFER_SIZE = 8192,
};

static volatile uint16_t *const vga = (volatile uint16_t *) 0xb8000;
static uint32_t row;
static uint32_t column;
static volatile uint32_t serial_read_head;
static volatile uint32_t serial_read_tail;
static volatile uint8_t serial_read_buffer[SERIAL_BUFFER_SIZE];

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

static void serial_init(void)
{
    outb(COM1 + 1, 0x00);
    outb(COM1 + 3, 0x80);
    outb(COM1 + 0, 0x03);
    outb(COM1 + 1, 0x00);
    outb(COM1 + 3, 0x03);
    outb(COM1 + 2, 0xc7);
    outb(COM1 + 4, 0x0b);
}

void typephp_os_console_enable_interrupts(void)
{
    /* Received-data-available interrupt. MCR.OUT2 was enabled by serial_init
     * and routes the UART interrupt to the legacy PIC's IRQ4 input. */
    outb(COM1 + 1, 0x01);
}

void typephp_os_console_interrupt(void)
{
    while ((inb(COM1 + 5) & 0x01u) != 0) {
        const uint8_t value = inb(COM1);
        const uint32_t next = (serial_read_head + 1u) % SERIAL_BUFFER_SIZE;
        if (next != serial_read_tail) {
            serial_read_buffer[serial_read_head] = value;
            serial_read_head = next;
        }
    }
}

static void serial_put(uint8_t value)
{
    while ((inb(COM1 + 5) & 0x20u) == 0) {
    }
    outb(COM1, value);
}

long typephp_os_console_read(void *buffer, unsigned long size)
{
    uint8_t *output = (uint8_t *) buffer;
    if (output == 0 || size == 0) {
        return 0;
    }
    /* The system remains single-task, but a blocking read can still halt the
     * CPU until IRQ4 supplies input. STI followed immediately by HLT avoids
     * the empty-buffer/check-to-sleep lost-wakeup race on x86. */
    for (;;) {
        __asm__ volatile("cli" : : : "memory");
        if (serial_read_tail != serial_read_head) {
            output[0] = serial_read_buffer[serial_read_tail];
            serial_read_tail = (serial_read_tail + 1u) % SERIAL_BUFFER_SIZE;
            return 1;
        }
        __asm__ volatile("sti; hlt; cli" : : : "memory");
    }
}

static void vga_scroll(void)
{
    if (row < VGA_HEIGHT) {
        return;
    }
    for (uint32_t y = 1; y < VGA_HEIGHT; y++) {
        for (uint32_t x = 0; x < VGA_WIDTH; x++) {
            vga[(y - 1) * VGA_WIDTH + x] = vga[y * VGA_WIDTH + x];
        }
    }
    for (uint32_t x = 0; x < VGA_WIDTH; x++) {
        vga[(VGA_HEIGHT - 1) * VGA_WIDTH + x] = (uint16_t) (0x0720u);
    }
    row = VGA_HEIGHT - 1;
}

void kernel_clear_c(int color)
{
    const uint16_t blank = (uint16_t) ((((uint16_t) color) << 8u) | ' ');
    for (uint32_t i = 0; i < VGA_WIDTH * VGA_HEIGHT; i++) {
        vga[i] = blank;
    }
    row = 0;
    column = 0;
}

void kernel_put_char_c(int ascii, int color)
{
    const uint8_t character = (uint8_t) ascii;
    if (character == '\n') {
        serial_put('\r');
        serial_put('\n');
        column = 0;
        row++;
        vga_scroll();
        return;
    }
    if (character == '\t') {
        do {
            kernel_put_char_c(' ', color);
        } while ((column & 7u) != 0);
        return;
    }

    serial_put(character);
    vga[row * VGA_WIDTH + column] =
        (uint16_t) ((((uint16_t) color) << 8u) | character);
    column++;
    if (column == VGA_WIDTH) {
        column = 0;
        row++;
        vga_scroll();
    }
}

void typephp_os_write(const char *data, unsigned long size)
{
    for (unsigned long index = 0; index < size; ++index) {
        kernel_put_char_c((unsigned char) data[index], 0x0f);
    }
}

void kernel_halt_c(void)
{
    // QEMU's isa-debug-exit device turns this into process exit status 33.
    outb(0xf4, 0x10);
    for (;;) {
        __asm__ volatile("cli; hlt");
    }
}

extern void typephp_kernel_main(void);
extern void physical_memory_init(uint64_t multiboot_info_address);

void kernel_entry(uint64_t magic, uint64_t multiboot_info)
{
    (void) multiboot_info;
    serial_init();
    if (magic != MULTIBOOT_BOOTLOADER_MAGIC) {
        const char *message = "Invalid Multiboot boot magic\n";
        while (*message != '\0') {
            serial_put((uint8_t) *message++);
        }
        kernel_halt_c();
    }
    physical_memory_init(multiboot_info);
    typephp_kernel_main();
    kernel_halt_c();
}
