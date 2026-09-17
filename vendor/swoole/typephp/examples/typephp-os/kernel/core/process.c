/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | Single foreground process, ELF64 loader and x86-64 syscall core.    |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

#include "typephp_os_abi.h"
#include "typephp_os_memory.h"
#include "typephp_os_syscall.h"
#include "vm.h"
#include "socket.h"
#include "net/network.h"

#include <errno.h>
#include <fcntl.h>
#include <stddef.h>
#include <stdint.h>
#include <string.h>
#include <sys/stat.h>
#include <unistd.h>

enum {
    USER_BEGIN = 0x40000000u,
    USER_COMMAND_BEGIN = 0x41000000u,
    USER_SHELL_END = USER_COMMAND_BEGIN,
    USER_COMMAND_END = 0x48000000u,
    USER_END = 0x50000000u,
    USER_STACK = USER_END - 16u,
    USER_COMMAND_STACK = USER_END - 16u,
    USER_STACK_SIZE = 1024u * 1024u,
    USER_HEAP_LIMIT = 0x4c000000u,
    USER_MMAP_BEGIN = USER_HEAP_LIMIT,
    USER_CODE_SELECTOR = 0x23,
    USER_DATA_SELECTOR = 0x1b,
    KERNEL_CODE_SELECTOR = 0x08,
    TSS_SELECTOR = 0x28,
    PIC_MASTER_COMMAND = 0x20,
    PIC_MASTER_DATA = 0x21,
    PIC_SLAVE_COMMAND = 0xa0,
    PIC_SLAVE_DATA = 0xa1,
    PIC_END_OF_INTERRUPT = 0x20,
    PIT_CHANNEL_0 = 0x40,
    PIT_COMMAND = 0x43,
    TIMER_FREQUENCY = 100,
    MAX_USER_ARGUMENTS = 8,
    USER_PATH_MAX = 128,
    UTSNAME_LENGTH = 65,
};

typedef struct {
    char sysname[UTSNAME_LENGTH];
    char nodename[UTSNAME_LENGTH];
    char release[UTSNAME_LENGTH];
    char version[UTSNAME_LENGTH];
    char machine[UTSNAME_LENGTH];
    char domainname[UTSNAME_LENGTH];
} user_utsname;

typedef struct {
    long seconds;
    long nanoseconds;
} user_timespec;

typedef struct {
    long seconds;
    long microseconds;
} user_timeval;

typedef struct {
    uint16_t rows;
    uint16_t columns;
    uint16_t pixel_width;
    uint16_t pixel_height;
} user_winsize;

enum {
    ELF_PT_LOAD = 1,
    ELF_PF_X = 1,
    ELF_PF_W = 2,
    ELF_ET_EXEC = 2,
    ELF_MACHINE_X86_64 = 62,
};

enum {
    PROT_READ_VALUE = 1,
    PROT_WRITE_VALUE = 2,
    PROT_EXEC_VALUE = 4,
    MAP_PRIVATE_VALUE = 2,
    MAP_ANONYMOUS_VALUE = 0x20,
};

typedef struct __attribute__((packed)) {
    unsigned char ident[16];
    uint16_t type;
    uint16_t machine;
    uint32_t version;
    uint64_t entry;
    uint64_t phoff;
    uint64_t shoff;
    uint32_t flags;
    uint16_t ehsize;
    uint16_t phentsize;
    uint16_t phnum;
    uint16_t shentsize;
    uint16_t shnum;
    uint16_t shstrndx;
} elf64_header;

typedef struct __attribute__((packed)) {
    uint32_t type;
    uint32_t flags;
    uint64_t offset;
    uint64_t vaddr;
    uint64_t paddr;
    uint64_t filesz;
    uint64_t memsz;
    uint64_t align;
} elf64_program_header;

typedef struct __attribute__((packed)) {
    uint16_t limit;
    uint64_t base;
} descriptor_pointer;

typedef struct __attribute__((packed)) {
    uint16_t offset_low;
    uint16_t selector;
    uint8_t ist;
    uint8_t attributes;
    uint16_t offset_middle;
    uint32_t offset_high;
    uint32_t reserved;
} idt_gate;

typedef struct __attribute__((packed)) {
    uint32_t reserved0;
    uint64_t rsp0;
    uint64_t rsp1;
    uint64_t rsp2;
    uint64_t reserved1;
    uint64_t ist[7];
    uint64_t reserved2;
    uint16_t reserved3;
    uint16_t iomap_base;
} task_state_segment;

typedef struct {
    uint64_t rax;
    uint64_t r15;
    uint64_t r14;
    uint64_t r13;
    uint64_t r12;
    uint64_t r11;
    uint64_t r10;
    uint64_t r9;
    uint64_t r8;
    uint64_t rbp;
    uint64_t rdi;
    uint64_t rsi;
    uint64_t rdx;
    uint64_t rcx;
    uint64_t rbx;
    uint64_t rip;
    uint64_t cs;
    uint64_t rflags;
    uint64_t rsp;
    uint64_t ss;
} syscall_frame;

typedef struct {
    uint64_t rax;
    uint64_t r15;
    uint64_t r14;
    uint64_t r13;
    uint64_t r12;
    uint64_t r11;
    uint64_t r10;
    uint64_t r9;
    uint64_t r8;
    uint64_t rbp;
    uint64_t rdi;
    uint64_t rsi;
    uint64_t rdx;
    uint64_t rcx;
    uint64_t rbx;
    uint64_t vector;
    uint64_t error;
    uint64_t rip;
    uint64_t cs;
    uint64_t rflags;
    uint64_t rsp;
    uint64_t ss;
} exception_frame;

typedef struct {
    uint64_t pid;
    int running;
    char cwd[USER_PATH_MAX];
    syscall_frame parent_frame;
    int parent_waiting;
    uint64_t address_space;
    uint64_t parent_address_space;
    uint64_t parent_free_pages;
    uint64_t program_break;
    uint64_t minimum_break;
    uint64_t mmap_cursor;
    uint64_t mmap_limit;
    uint64_t parent_program_break;
    uint64_t parent_minimum_break;
    uint64_t parent_mmap_cursor;
    uint64_t parent_mmap_limit;
} process_state;

extern void typephp_os_syscall_entry(void);
extern void typephp_os_enter_user(uint64_t entry, uint64_t stack);
extern long typephp_os_time_seconds(void);
extern void typephp_os_exception_0(void);
extern void typephp_os_exception_3(void);
extern void typephp_os_exception_5(void);
extern void typephp_os_exception_6(void);
extern void typephp_os_exception_10(void);
extern void typephp_os_exception_11(void);
extern void typephp_os_exception_12(void);
extern void typephp_os_exception_13(void);
extern void typephp_os_exception_14(void);
extern void typephp_os_irq_0(void);
extern void typephp_os_irq_4(void);
extern int rename(const char *old_path, const char *new_path);
extern uint64_t physical_page_available(void);
extern uint64_t physical_page_total(void);
extern uint64_t physical_memory_bytes(void);
extern uint64_t physical_kernel_reserved_bytes(void);
extern unsigned long typephp_os_disk_cache_bytes(void);

static uint64_t gdt[7] __attribute__((aligned(16)));
static idt_gate idt[256] __attribute__((aligned(16)));
static task_state_segment tss;
unsigned char typephp_os_syscall_stack[64u * 1024u] __attribute__((aligned(16)));
uint64_t typephp_os_syscall_user_rsp;
static process_state foreground_process = {.pid = 1, .cwd = "/"};
static volatile uint64_t timer_ticks;

uint64_t typephp_os_timer_milliseconds(void)
{
    return timer_ticks * (1000u / TIMER_FREQUENCY);
}

enum {
    IA32_EFER = 0xc0000080u,
    IA32_STAR = 0xc0000081u,
    IA32_LSTAR = 0xc0000082u,
    IA32_FMASK = 0xc0000084u,
};

static uint64_t read_msr(uint32_t index)
{
    uint32_t low;
    uint32_t high;
    __asm__ volatile("rdmsr" : "=a"(low), "=d"(high) : "c"(index));
    return ((uint64_t) high << 32u) | low;
}

static void outb(uint16_t port, uint8_t value)
{
    __asm__ volatile("outb %0, %1" : : "a"(value), "Nd"(port));
}

static void io_wait(void)
{
    outb(0x80, 0);
}

static void install_interrupt_controller(void)
{
    const uint16_t divisor = (uint16_t) (1193182u / TIMER_FREQUENCY);

    /* Remap the legacy PIC away from the CPU exception vectors. */
    outb(PIC_MASTER_COMMAND, 0x11);
    io_wait();
    outb(PIC_SLAVE_COMMAND, 0x11);
    io_wait();
    outb(PIC_MASTER_DATA, 0x20);
    io_wait();
    outb(PIC_SLAVE_DATA, 0x28);
    io_wait();
    outb(PIC_MASTER_DATA, 0x04);
    io_wait();
    outb(PIC_SLAVE_DATA, 0x02);
    io_wait();
    outb(PIC_MASTER_DATA, 0x01);
    io_wait();
    outb(PIC_SLAVE_DATA, 0x01);
    io_wait();

    /* A 100 Hz periodic PIT is sufficient for the single-task sleep ABI. */
    outb(PIT_COMMAND, 0x36);
    outb(PIT_CHANNEL_0, (uint8_t) divisor);
    outb(PIT_CHANNEL_0, (uint8_t) (divisor >> 8u));
    typephp_os_console_enable_interrupts();

    /* IRQ0 (PIT) and IRQ4 (COM1) only. The slave PIC stays fully masked. */
    outb(PIC_SLAVE_DATA, 0xff);
    outb(PIC_MASTER_DATA, (uint8_t) ~(UINT8_C(1) | UINT8_C(1) << 4u));
}

static void pic_end_of_interrupt(unsigned int irq)
{
    if (irq >= 8u) {
        outb(PIC_SLAVE_COMMAND, PIC_END_OF_INTERRUPT);
    }
    outb(PIC_MASTER_COMMAND, PIC_END_OF_INTERRUPT);
}

static void write_msr(uint32_t index, uint64_t value)
{
    __asm__ volatile("wrmsr" : : "c"(index), "a"((uint32_t) value),
        "d"((uint32_t) (value >> 32u)) : "memory");
}

static void panic(const char *message)
{
    typephp_os_write("Process panic: ", sizeof("Process panic: ") - 1);
    typephp_os_panic(message);
}

static int range_inside(uint64_t address, uint64_t size, uint64_t begin, uint64_t end)
{
    return address >= begin && size <= end - begin && address <= end - size;
}

static uint64_t page_align_up(uint64_t value)
{
    return (value + UINT64_C(4095)) & ~UINT64_C(4095);
}

static uint64_t page_align_down(uint64_t value)
{
    return value & ~UINT64_C(4095);
}

static int user_buffer(const void *pointer, size_t size)
{
    return typephp_vm_user_range(typephp_vm_current(),
        (uint64_t) (uintptr_t) pointer, size, 0);
}

static size_t bounded_user_string(const char *string, size_t maximum)
{
    size_t length = 0;
    if (!user_buffer(string, 1)) {
        return (size_t) -1;
    }
    while (length < maximum && user_buffer(string + length, 1) && string[length] != '\0') {
        ++length;
    }
    return length < maximum && user_buffer(string + length, 1) ? length : (size_t) -1;
}

static int cpu_has_rdrand(void)
{
    uint32_t eax;
    uint32_t ebx;
    uint32_t ecx;
    uint32_t edx;
    __asm__ volatile("cpuid"
        : "=a"(eax), "=b"(ebx), "=c"(ecx), "=d"(edx)
        : "a"(1u), "c"(0u));
    (void) eax;
    (void) ebx;
    (void) edx;
    return (ecx & (1u << 30u)) != 0;
}

static int cpu_random_u64(uint64_t *value)
{
    unsigned char valid;
    int attempt;
    for (attempt = 0; attempt < 16; ++attempt) {
        __asm__ volatile("rdrand %0; setc %1"
            : "=r"(*value), "=qm"(valid));
        if (valid) {
            return 1;
        }
        __asm__ volatile("pause");
    }
    return 0;
}

static long syscall_getrandom(void *buffer, size_t size, unsigned int flags)
{
    unsigned char *output = (unsigned char *) buffer;
    if ((flags & ~1u) != 0) {
        return -EINVAL;
    }
    if (!user_buffer(buffer, size)) {
        return -EFAULT;
    }
    if (!cpu_has_rdrand()) {
        return -ENOSYS;
    }
    while (size != 0) {
        uint64_t random;
        const size_t chunk = size < sizeof(random) ? size : sizeof(random);
        if (!cpu_random_u64(&random)) {
            return -EAGAIN;
        }
        memcpy(output, &random, chunk);
        output += chunk;
        size -= chunk;
    }
    return (long) (output - (unsigned char *) buffer);
}

static void install_tss_descriptor(uint64_t base, uint32_t limit)
{
    gdt[5] = ((uint64_t) (limit & 0xffffu))
        | ((base & 0xffffffu) << 16u)
        | (UINT64_C(0x89) << 40u)
        | ((uint64_t) ((limit >> 16u) & 0x0fu) << 48u)
        | (((base >> 24u) & 0xffu) << 56u);
    gdt[6] = base >> 32u;
}

static void install_idt_gate(unsigned int vector, void (*entry)(void), uint8_t attributes)
{
    uintptr_t handler = (uintptr_t) entry;
    idt[vector].offset_low = handler & 0xffffu;
    idt[vector].selector = KERNEL_CODE_SELECTOR;
    idt[vector].attributes = attributes;
    idt[vector].offset_middle = (handler >> 16u) & 0xffffu;
    idt[vector].offset_high = handler >> 32u;
}

static void install_descriptor_tables(void)
{
    descriptor_pointer pointer;

    memset(gdt, 0, sizeof(gdt));
    gdt[1] = UINT64_C(0x00af9a000000ffff);
    gdt[2] = UINT64_C(0x00cf92000000ffff);
    gdt[3] = UINT64_C(0x00cff2000000ffff);
    gdt[4] = UINT64_C(0x00affa000000ffff);
    memset(&tss, 0, sizeof(tss));
    tss.rsp0 = (uint64_t) (uintptr_t)
        (typephp_os_syscall_stack + sizeof(typephp_os_syscall_stack));
    tss.iomap_base = sizeof(tss);
    install_tss_descriptor((uint64_t) (uintptr_t) &tss, sizeof(tss) - 1u);

    pointer.limit = sizeof(gdt) - 1u;
    pointer.base = (uint64_t) (uintptr_t) gdt;
    __asm__ volatile("lgdt %0" : : "m"(pointer) : "memory");
    __asm__ volatile(
        "mov %[data], %%ax\n"
        "mov %%ax, %%ds\n"
        "mov %%ax, %%es\n"
        "mov %%ax, %%ss\n"
        "ltr %[tss]\n"
        :
        : [data] "i"(0x10), [tss] "r"((uint16_t) TSS_SELECTOR)
        : "rax", "memory");

    memset(idt, 0, sizeof(idt));
    install_idt_gate(0, typephp_os_exception_0, 0x8e);
    install_idt_gate(3, typephp_os_exception_3, 0xee);
    install_idt_gate(5, typephp_os_exception_5, 0x8e);
    install_idt_gate(6, typephp_os_exception_6, 0x8e);
    install_idt_gate(10, typephp_os_exception_10, 0x8e);
    install_idt_gate(11, typephp_os_exception_11, 0x8e);
    install_idt_gate(12, typephp_os_exception_12, 0x8e);
    install_idt_gate(13, typephp_os_exception_13, 0x8e);
    install_idt_gate(14, typephp_os_exception_14, 0x8e);
    install_idt_gate(32, typephp_os_irq_0, 0x8e);
    install_idt_gate(36, typephp_os_irq_4, 0x8e);
    pointer.limit = sizeof(idt) - 1u;
    pointer.base = (uint64_t) (uintptr_t) idt;
    __asm__ volatile("lidt %0" : : "m"(pointer) : "memory");

    /* STAR[47:32] selects kernel CS 0x08 and its following SS 0x10.
     * SYSRET's selector arithmetic starts at 0x10, yielding user SS 0x1b
     * and user CS 0x23. This kernel returns with IRETQ so that spawn/exit can
     * replace the complete user context, but the entry ABI remains the native
     * Linux x86-64 SYSCALL register convention. */
    write_msr(IA32_STAR,
        (UINT64_C(0x10) << 48u) | ((uint64_t) KERNEL_CODE_SELECTOR << 32u));
    write_msr(IA32_LSTAR, (uint64_t) (uintptr_t) typephp_os_syscall_entry);
    write_msr(IA32_FMASK, UINT64_C(0x700)); /* TF, IF and DF */
    write_msr(IA32_EFER, read_msr(IA32_EFER) | UINT64_C(1));
    install_interrupt_controller();
}

static int read_exact(int fd, void *buffer, size_t size)
{
    unsigned char *output = (unsigned char *) buffer;
    while (size != 0) {
        /* The TypePHP filesystem bridge returns each read as a PHP string.
         * Bound the temporary allocation while loading multi-megabyte Nano
         * executables instead of requesting an entire ELF segment at once. */
        const size_t request = size > 16u * 1024u ? 16u * 1024u : size;
        ssize_t result = read(fd, output, request);
        if (result < 0) {
            return -errno;
        }
        if (result == 0) {
            return -ENOEXEC;
        }
        output += result;
        size -= (size_t) result;
    }
    return 0;
}

static int seek_and_read(int fd, uint64_t offset, void *buffer, size_t size)
{
    if (offset > INT64_MAX || lseek(fd, (off_t) offset, SEEK_SET) < 0) {
        return -errno;
    }
    return read_exact(fd, buffer, size);
}

static int load_user_elf_file(
    const char *path, uint64_t address_space,
    uint64_t region_begin, uint64_t region_end,
    uint64_t *entry, uint64_t *image_end)
{
    elf64_header header;
    uint64_t image_size;
    int fd = open(path, O_RDONLY);
    int result = 0;
    int entry_loaded = 0;
    uint64_t loaded_end = region_begin;

    if (fd < 0) {
        return -errno;
    }
    {
        off_t end = lseek(fd, 0, SEEK_END);
        if (end < 0) {
            result = -errno;
            goto done;
        }
        image_size = (uint64_t) end;
    }
    if (image_size < sizeof(header)) {
        result = -ENOEXEC;
        goto done;
    }
    result = seek_and_read(fd, 0, &header, sizeof(header));
    if (result < 0) {
        goto done;
    }
    if (header.ident[0] != 0x7f || header.ident[1] != 'E'
        || header.ident[2] != 'L' || header.ident[3] != 'F'
        || header.ident[4] != 2 || header.ident[5] != 1
        || header.type != ELF_ET_EXEC || header.machine != ELF_MACHINE_X86_64
        || header.phentsize != sizeof(elf64_program_header)
        || header.phnum == 0 || header.phnum > 64
        || !range_inside(header.phoff,
            (uint64_t) header.phnum * sizeof(elf64_program_header), 0, image_size)) {
        result = -ENOEXEC;
        goto done;
    }

    for (uint16_t index = 0; index < header.phnum; ++index) {
        elf64_program_header segment;
        result = seek_and_read(fd,
            header.phoff + (uint64_t) index * sizeof(segment), &segment, sizeof(segment));
        if (result < 0) {
            goto done;
        }
        if (segment.type != ELF_PT_LOAD) {
            continue;
        }
        /* GNU ld can retain an empty PT_LOAD from the linker script when a
         * tiny static executable has no data or bss sections. It maps no
         * bytes and must not be rejected merely because its placeholder
         * virtual address is zero. */
        if (segment.filesz == 0 && segment.memsz == 0) {
            continue;
        }
        if (segment.filesz > segment.memsz
            || !range_inside(segment.offset, segment.filesz, 0, image_size)
            || !range_inside(segment.vaddr, segment.memsz, region_begin, region_end)) {
            result = -ENOEXEC;
            goto done;
        }
        /* The loader needs temporary write access while copying and clearing
         * the segment. The final ELF permissions are installed below. */
        if (segment.memsz != 0 && !typephp_vm_map_user(address_space,
            segment.vaddr, segment.memsz,
            TYPEPHP_VM_USER_WRITE
                | ((segment.flags & ELF_PF_X) != 0 ? TYPEPHP_VM_USER_EXECUTE : 0))) {
            result = -ENOMEM;
            goto done;
        }
        if (segment.vaddr + segment.memsz > loaded_end) {
            loaded_end = segment.vaddr + segment.memsz;
        }
        result = seek_and_read(fd, segment.offset,
            (void *) (uintptr_t) segment.vaddr, (size_t) segment.filesz);
        if (result < 0) {
            goto done;
        }
        memset((void *) (uintptr_t) (segment.vaddr + segment.filesz),
            0, (size_t) (segment.memsz - segment.filesz));
        if (segment.memsz != 0 && !typephp_vm_protect_user(address_space,
            segment.vaddr, segment.memsz,
            ((segment.flags & ELF_PF_W) != 0 ? TYPEPHP_VM_USER_WRITE : 0)
                | ((segment.flags & ELF_PF_X) != 0
                    ? TYPEPHP_VM_USER_EXECUTE : 0))) {
            result = -ENOMEM;
            goto done;
        }
        if ((segment.flags & ELF_PF_X) != 0
            && range_inside(header.entry, 1, segment.vaddr, segment.vaddr + segment.memsz)) {
            entry_loaded = 1;
        }
    }
    if (!entry_loaded || !range_inside(header.entry, 1, region_begin, region_end)) {
        result = -ENOEXEC;
        goto done;
    }
    *entry = header.entry;
    *image_end = loaded_end;

done:
    if (close(fd) < 0 && result == 0) {
        result = -errno;
    }
    return result;
}

static uint64_t prepare_initial_stack(
    uint64_t stack_top, size_t argc, const char *const argv[])
{
    uint64_t stack = stack_top;
    uint64_t addresses[MAX_USER_ARGUMENTS];
    uint64_t *words;
    size_t index = 0;

    index = argc;
    while (index != 0) {
        size_t length;
        --index;
        length = strlen(argv[index]) + 1u;
        stack -= length;
        memcpy((void *) (uintptr_t) stack, argv[index], length);
        addresses[index] = stack;
    }

    /* argc, argv[], NULL, envp NULL, then an AT_NULL auxv entry. */
    stack = (stack - (argc + 5u) * sizeof(uint64_t)) & ~UINT64_C(0x0f);
    words = (uint64_t *) (uintptr_t) stack;
    words[index++] = argc;
    for (size_t argument = 0; argument < argc; ++argument) {
        words[index++] = addresses[argument];
    }
    words[index++] = 0;
    words[index++] = 0;
    words[index++] = 0;
    words[index] = 0;
    return stack;
}

static int command_path(const char *name, char *path, size_t capacity)
{
    size_t length = strlen(name);
    if (length == 0 || length > 8 || capacity < length + sizeof("/BIN/.ELF")) {
        return 0;
    }
    memcpy(path, "/BIN/", sizeof("/BIN/") - 1u);
    for (size_t index = 0; index < length; ++index) {
        unsigned char character = (unsigned char) name[index];
        if (!((character >= 'a' && character <= 'z')
                || (character >= 'A' && character <= 'Z')
                || (character >= '0' && character <= '9')
                || character == '_' || character == '-')) {
            return 0;
        }
        path[index + sizeof("/BIN/") - 1u] = (char) character;
    }
    memcpy(path + length + sizeof("/BIN/") - 1u, ".ELF", sizeof(".ELF"));
    return 1;
}

static int resolved_path(const char *path, char *resolved, size_t capacity);

static long syscall_getcwd(char *buffer, size_t size)
{
    size_t length = strlen(foreground_process.cwd) + 1u;
    if (!user_buffer(buffer, size)) {
        return -EFAULT;
    }
    if (size < length) {
        return -ERANGE;
    }
    memcpy(buffer, foreground_process.cwd, length);
    return (long) length;
}

static long syscall_chdir(const char *path)
{
    char normalized[USER_PATH_MAX];
    if (!resolved_path(path, normalized, sizeof(normalized))) {
        return -ENOENT;
    }
    {
        long type = typephp_os_fs_path_type(normalized);
        if (type == 0) {
            return -ENOENT;
        }
        if (type != 2) {
            return -ENOTDIR;
        }
    }
    memcpy(foreground_process.cwd, normalized, strlen(normalized) + 1u);
    return 0;
}

static int resolved_path(const char *path, char *resolved, size_t capacity)
{
    size_t length = bounded_user_string(path, capacity);
    size_t input = 0;
    size_t output;
    if (length == (size_t) -1 || length == 0) {
        return 0;
    }
    if (capacity < 2) {
        return 0;
    }
    if (path[0] == '/') {
        resolved[0] = '/';
        output = 1;
    } else {
        output = strlen(foreground_process.cwd);
        if (output + 1u > capacity) {
            return 0;
        }
        memcpy(resolved, foreground_process.cwd, output);
    }
    while (input < length) {
        size_t start;
        size_t component_length;
        while (input < length && path[input] == '/') {
            ++input;
        }
        if (input == length) {
            break;
        }
        start = input;
        while (input < length && path[input] != '/') {
            ++input;
        }
        component_length = input - start;
        if (component_length == 1 && path[start] == '.') {
            continue;
        }
        if (component_length == 2 && path[start] == '.' && path[start + 1u] == '.') {
            while (output > 1 && resolved[output - 1u] != '/') {
                --output;
            }
            if (output > 1) {
                --output;
            }
            continue;
        }
        if (component_length > 12u) {
            return 0;
        }
        if (output > 1) {
            if (output + 1u >= capacity) {
                return 0;
            }
            resolved[output++] = '/';
        }
        if (output + component_length + 1u > capacity) {
            return 0;
        }
        memcpy(resolved + output, path + start, component_length);
        output += component_length;
    }
    resolved[output] = '\0';
    return 1;
}

static long posix_syscall_result(long result)
{
    return result < 0 ? -errno : result;
}

static long syscall_openat(long directory_fd, const char *path, int flags, int mode)
{
    char resolved[USER_PATH_MAX];
    int fd;
    if ((int) directory_fd != AT_FDCWD && (path == 0 || path[0] != '/')) {
        return -EBADF;
    }
    if (!resolved_path(path, resolved, sizeof(resolved))) {
        return -EFAULT;
    }
    fd = open(resolved, flags, mode);
    return posix_syscall_result(fd);
}

static long syscall_stat_path(const char *path, struct stat *result, int no_follow)
{
    char resolved[USER_PATH_MAX];
    if (!user_buffer(result, sizeof(*result))) {
        return -EFAULT;
    }
    if (!resolved_path(path, resolved, sizeof(resolved))) {
        return -EFAULT;
    }
    return posix_syscall_result(no_follow
        ? lstat(resolved, result) : stat(resolved, result));
}

static long syscall_fstat(int fd, struct stat *result)
{
    if (!user_buffer(result, sizeof(*result))) {
        return -EFAULT;
    }
    return posix_syscall_result(fstat(fd, result));
}

static long syscall_newfstatat(
    int directory_fd, const char *path, struct stat *result, int flags)
{
    enum { AT_SYMLINK_NOFOLLOW_VALUE = 0x100 };
    if ((flags & ~AT_SYMLINK_NOFOLLOW_VALUE) != 0) {
        return -EINVAL;
    }
    if (directory_fd != AT_FDCWD && (path == 0 || path[0] != '/')) {
        return -EBADF;
    }
    return syscall_stat_path(path, result,
        (flags & AT_SYMLINK_NOFOLLOW_VALUE) != 0);
}

static long syscall_access_path(
    int directory_fd, const char *path, int mode, int flags)
{
    enum { AT_EACCESS_VALUE = 0x200 };
    char resolved[USER_PATH_MAX];
    struct stat value;
    if ((mode & ~7) != 0 || (flags & ~AT_EACCESS_VALUE) != 0) {
        return -EINVAL;
    }
    if (directory_fd != AT_FDCWD && (path == 0 || path[0] != '/')) {
        return -EBADF;
    }
    if (!resolved_path(path, resolved, sizeof(resolved))) {
        return -EFAULT;
    }
    if (stat(resolved, &value) < 0) {
        return -errno;
    }
    /* One root identity, no ACLs. FAT16 files are readable and writable;
     * directories additionally carry an executable/search bit. */
    if ((mode & 4) != 0 && (value.st_mode & 0444) == 0) {
        return -EACCES;
    }
    if ((mode & 2) != 0 && (value.st_mode & 0222) == 0) {
        return -EACCES;
    }
    if ((mode & 1) != 0 && (value.st_mode & 0111) == 0) {
        return -EACCES;
    }
    return 0;
}

static long syscall_truncate(const char *path, off_t length)
{
    char resolved[USER_PATH_MAX];
    int fd;
    int result;
    if (length < 0) {
        return -EINVAL;
    }
    if (!resolved_path(path, resolved, sizeof(resolved))) {
        return -EFAULT;
    }
    fd = open(resolved, O_WRONLY);
    if (fd < 0) {
        return -errno;
    }
    result = ftruncate(fd, length);
    if (close(fd) < 0 && result == 0) {
        result = -1;
    }
    return posix_syscall_result(result);
}

static long syscall_time(long *result)
{
    long seconds = typephp_os_time_seconds();
    if (result != 0) {
        if (!user_buffer(result, sizeof(*result))) {
            return -EFAULT;
        }
        *result = seconds;
    }
    return seconds;
}

static long syscall_gettimeofday(user_timeval *result)
{
    if (!user_buffer(result, sizeof(*result))) {
        return -EFAULT;
    }
    result->seconds = typephp_os_time_seconds();
    result->microseconds = 0;
    return 0;
}

static long syscall_clock(int clock_id, user_timespec *result, int resolution)
{
    uint64_t ticks;
    if (clock_id != 0 && clock_id != 1) {
        return -EINVAL;
    }
    if (resolution && result == 0) {
        return 0;
    }
    if (!user_buffer(result, sizeof(*result))) {
        return -EFAULT;
    }
    if (resolution) {
        result->seconds = clock_id == 0 ? 1 : 0;
        result->nanoseconds = clock_id == 0
            ? 0
            : 1000000000L / TIMER_FREQUENCY;
        return 0;
    }
    if (clock_id == 0) {
        result->seconds = typephp_os_time_seconds();
        result->nanoseconds = 0;
        return 0;
    }
    ticks = timer_ticks;
    result->seconds = (long) (ticks / TIMER_FREQUENCY);
    result->nanoseconds = (long) (ticks % TIMER_FREQUENCY)
        * (1000000000L / TIMER_FREQUENCY);
    return 0;
}

static long syscall_nanosleep(
    const user_timespec *requested, user_timespec *remaining)
{
    uint64_t seconds;
    uint64_t subsecond_ticks;
    uint64_t duration_ticks;
    uint64_t deadline;
    long nanoseconds;

    if (!user_buffer(requested, sizeof(*requested))) {
        return -EFAULT;
    }
    if (remaining != 0 && !user_buffer(remaining, sizeof(*remaining))) {
        return -EFAULT;
    }
    if (requested->seconds < 0 || requested->nanoseconds < 0
        || requested->nanoseconds >= 1000000000L) {
        return -EINVAL;
    }
    seconds = (uint64_t) requested->seconds;
    nanoseconds = requested->nanoseconds;
    subsecond_ticks = ((uint64_t) nanoseconds
        + (1000000000u / TIMER_FREQUENCY) - 1u)
        / (1000000000u / TIMER_FREQUENCY);
    if (seconds > (UINT64_MAX - subsecond_ticks) / TIMER_FREQUENCY) {
        return -EINVAL;
    }
    duration_ticks = seconds * TIMER_FREQUENCY + subsecond_ticks;
    if (duration_ticks > INT64_MAX) {
        return -EINVAL;
    }
    deadline = timer_ticks + duration_ticks;
    while ((int64_t) (timer_ticks - deadline) < 0) {
        /* The current single foreground process sleeps inside its syscall.
         * IRQ0 advances timer_ticks and wakes HLT; no scheduler is required. */
        __asm__ volatile("sti; hlt; cli" : : : "memory");
    }
    if (remaining != 0) {
        remaining->seconds = 0;
        remaining->nanoseconds = 0;
    }
    return 0;
}

static long syscall_uname(user_utsname *result)
{
    static const user_utsname identity = {
        "TypePHP-OS",
        "typephp-os",
        "0.1",
        "TypePHP Nano user mode",
        "x86_64",
        "localdomain",
    };
    if (!user_buffer(result, sizeof(*result))) {
        return -EFAULT;
    }
    memcpy(result, &identity, sizeof(*result));
    return 0;
}

static long syscall_ioctl(int fd, unsigned long request, void *argument)
{
    enum {
        LINUX_TIOCGWINSZ = 0x5413,
        LINUX_TIOCNOTTY = 0x5422,
    };

    if (fd < STDIN_FILENO || fd > STDERR_FILENO) {
        return -ENOTTY;
    }
    switch (request) {
    case LINUX_TIOCGWINSZ: {
        user_winsize *window = (user_winsize *) argument;
        if (!user_buffer(window, sizeof(*window))) {
            return -EFAULT;
        }
        window->rows = 25;
        window->columns = 80;
        window->pixel_width = 0;
        window->pixel_height = 0;
        return 0;
    }
    case LINUX_TIOCNOTTY:
    default:
        return -ENOTTY;
    }
}

static long syscall_memory_info(typephp_os_memory_info *result)
{
    typephp_os_memory_info info;
    if (!user_buffer(result, sizeof(*result))) {
        return -EFAULT;
    }
    info.total_bytes = physical_memory_bytes();
    info.kernel_reserved_bytes = physical_kernel_reserved_bytes();
    info.zend_total_bytes = typephp_os_memory_total();
    info.zend_free_bytes = typephp_os_memory_available();
    info.page_total_bytes = physical_page_total() * UINT64_C(4096);
    info.page_free_bytes = physical_page_available() * UINT64_C(4096);
    info.block_cache_bytes = typephp_os_disk_cache_bytes();
    memcpy(result, &info, sizeof(info));
    return 0;
}

static unsigned int vm_protection(int protection)
{
    unsigned int flags = 0;
    if (protection == 0) {
        return TYPEPHP_VM_USER_NONE;
    }
    if ((protection & PROT_WRITE_VALUE) != 0) {
        flags |= TYPEPHP_VM_USER_WRITE;
    }
    if ((protection & PROT_EXEC_VALUE) != 0) {
        flags |= TYPEPHP_VM_USER_EXECUTE;
    }
    return flags;
}

static long syscall_brk(uint64_t requested)
{
    const uint64_t old_break = foreground_process.program_break;
    uint64_t old_page;
    uint64_t new_page;
    if (requested == 0) {
        return (long) old_break;
    }
    if (requested < foreground_process.minimum_break || requested > USER_HEAP_LIMIT) {
        return (long) old_break;
    }
    old_page = page_align_up(old_break);
    new_page = page_align_up(requested);
    if (new_page > old_page) {
        const uint64_t size = new_page - old_page;
        if (!typephp_vm_user_range_free(
                foreground_process.address_space, old_page, size)
            || !typephp_vm_map_user(foreground_process.address_space,
                old_page, size, TYPEPHP_VM_USER_WRITE)) {
            (void) typephp_vm_unmap_user(
                foreground_process.address_space, old_page, size);
            return (long) old_break;
        }
    } else if (old_page > new_page) {
        (void) typephp_vm_unmap_user(
            foreground_process.address_space, new_page, old_page - new_page);
    }
    foreground_process.program_break = requested;
    return (long) requested;
}

static long syscall_mmap(
    uint64_t address, uint64_t length, int protection,
    int flags, int fd, uint64_t offset)
{
    uint64_t size;
    uint64_t selected;
    uint64_t previous_cursor = foreground_process.mmap_cursor;
    const int supported_flags = MAP_PRIVATE_VALUE | MAP_ANONYMOUS_VALUE;
    if (length == 0 || length > UINT64_MAX - UINT64_C(4095)
        || (protection & ~(PROT_READ_VALUE | PROT_WRITE_VALUE | PROT_EXEC_VALUE)) != 0
        || (flags & supported_flags) != supported_flags
        || (flags & ~supported_flags) != 0 || fd != -1 || offset != 0) {
        return -EINVAL;
    }
    size = page_align_up(length);
    if (address != 0) {
        selected = page_align_down(address);
        if (selected < USER_MMAP_BEGIN
            || selected > foreground_process.mmap_limit
            || size > foreground_process.mmap_limit - selected
            || !typephp_vm_user_range_free(
                foreground_process.address_space, selected, size)) {
            selected = 0;
        }
    } else {
        selected = 0;
    }
    if (selected == 0) {
        if (size > foreground_process.mmap_cursor - USER_MMAP_BEGIN) {
            return -ENOMEM;
        }
        selected = page_align_down(foreground_process.mmap_cursor - size);
        if (selected < USER_MMAP_BEGIN
            || !typephp_vm_user_range_free(
                foreground_process.address_space, selected, size)) {
            return -ENOMEM;
        }
        foreground_process.mmap_cursor = selected;
    }
    if (!typephp_vm_map_user(foreground_process.address_space,
        selected, size, vm_protection(protection))) {
        (void) typephp_vm_unmap_user(
            foreground_process.address_space, selected, size);
        foreground_process.mmap_cursor = previous_cursor;
        return -ENOMEM;
    }
    return (long) selected;
}

static long syscall_munmap(uint64_t address, uint64_t length)
{
    uint64_t size;
    if ((address & UINT64_C(4095)) != 0 || length == 0
        || length > UINT64_MAX - UINT64_C(4095)) {
        return -EINVAL;
    }
    size = page_align_up(length);
    if (address < USER_MMAP_BEGIN || address > foreground_process.mmap_limit
        || size > foreground_process.mmap_limit - address) {
        return -EINVAL;
    }
    return typephp_vm_unmap_user(
        foreground_process.address_space, address, size) ? 0 : -EINVAL;
}

static long syscall_mprotect(uint64_t address, uint64_t length, int protection)
{
    if ((address & UINT64_C(4095)) != 0 || length == 0
        || length > UINT64_MAX - UINT64_C(4095)
        || (protection & ~(PROT_READ_VALUE | PROT_WRITE_VALUE | PROT_EXEC_VALUE)) != 0) {
        return -EINVAL;
    }
    return typephp_vm_protect_user(foreground_process.address_space,
        address, page_align_up(length), vm_protection(protection)) ? 0 : -ENOMEM;
}

static long syscall_spawn(syscall_frame *frame, const char *const *arguments)
{
    uint64_t entry;
    uint64_t child_address_space;
    uint64_t parent_address_space;
    uint64_t image_end;
    char command[9];
    char path[32];
    char argument_storage[MAX_USER_ARGUMENTS][65];
    const char *kernel_arguments[MAX_USER_ARGUMENTS];
    size_t name_length;
    size_t argc = 0;

    if (foreground_process.parent_waiting) {
        return -1;
    }
    while (argc < MAX_USER_ARGUMENTS) {
        if (!user_buffer(arguments + argc, sizeof(*arguments))) {
            return -1;
        }
        if (arguments[argc] == 0) {
            break;
        }
        size_t length = bounded_user_string(arguments[argc], 64);
        if (length == (size_t) -1) {
            return -1;
        }
        memcpy(argument_storage[argc], arguments[argc], length + 1u);
        kernel_arguments[argc] = argument_storage[argc];
        ++argc;
    }
    if (argc == 0 || argc == MAX_USER_ARGUMENTS) {
        return -1;
    }
    name_length = strlen(kernel_arguments[0]);
    if (name_length == 0 || name_length >= sizeof(command)) {
        return -1;
    }
    memcpy(command, kernel_arguments[0], name_length + 1u);
    if (!command_path(command, path, sizeof(path))) {
        return -ENOENT;
    }

    foreground_process.parent_free_pages = physical_page_available();
    child_address_space = typephp_vm_create();
    if (child_address_space == 0
        || !typephp_vm_map_user(child_address_space,
            USER_COMMAND_STACK + 16u - USER_STACK_SIZE,
            USER_STACK_SIZE, TYPEPHP_VM_USER_WRITE)) {
        if (child_address_space != 0) {
            typephp_vm_destroy(child_address_space);
        }
        return -ENOMEM;
    }
    parent_address_space = typephp_vm_current();
    typephp_vm_activate(child_address_space);
    {
        int result = load_user_elf_file(
            path, child_address_space,
            USER_COMMAND_BEGIN, USER_COMMAND_END, &entry, &image_end);
        if (result < 0) {
            typephp_vm_activate(parent_address_space);
            typephp_vm_destroy(child_address_space);
            return result;
        }
    }
    memcpy(&foreground_process.parent_frame, frame, sizeof(*frame));
    foreground_process.parent_waiting = 1;
    foreground_process.pid = 2;
    foreground_process.parent_address_space = parent_address_space;
    foreground_process.address_space = child_address_space;
    foreground_process.parent_program_break = foreground_process.program_break;
    foreground_process.parent_minimum_break = foreground_process.minimum_break;
    foreground_process.parent_mmap_cursor = foreground_process.mmap_cursor;
    foreground_process.parent_mmap_limit = foreground_process.mmap_limit;
    foreground_process.minimum_break = image_end;
    foreground_process.program_break = image_end;
    foreground_process.mmap_limit = page_align_down(
        USER_COMMAND_STACK + 16u - USER_STACK_SIZE);
    foreground_process.mmap_cursor = foreground_process.mmap_limit;
    frame->rip = entry;
    frame->rsp = prepare_initial_stack(USER_COMMAND_STACK, argc, kernel_arguments);
    frame->rdi = 0;
    frame->rsi = 0;
    frame->rdx = 0;
    return 0;
}

static long syscall_exit(syscall_frame *frame, long status)
{
    uint64_t child_address_space;
    if (!foreground_process.parent_waiting) {
        foreground_process.running = 0;
        typephp_os_panic("shell process exited\n");
    }
    child_address_space = foreground_process.address_space;
    typephp_socket_close_owner(foreground_process.pid);
    memcpy(frame, &foreground_process.parent_frame, sizeof(*frame));
    foreground_process.address_space = foreground_process.parent_address_space;
    foreground_process.program_break = foreground_process.parent_program_break;
    foreground_process.minimum_break = foreground_process.parent_minimum_break;
    foreground_process.mmap_cursor = foreground_process.parent_mmap_cursor;
    foreground_process.mmap_limit = foreground_process.parent_mmap_limit;
    foreground_process.parent_waiting = 0;
    foreground_process.pid = 1;
    typephp_vm_activate(foreground_process.address_space);
    typephp_vm_destroy(child_address_space);
    if (physical_page_available() != foreground_process.parent_free_pages) {
        panic("user address-space page leak\n");
    }
    return status;
}

static void write_unsigned(uint64_t value)
{
    char digits[20];
    size_t count = 0;
    do {
        digits[count++] = (char) ('0' + value % 10u);
        value /= 10u;
    } while (value != 0);
    while (count != 0) {
        --count;
        typephp_os_write(&digits[count], 1);
    }
}

static void write_hex(uint64_t value)
{
    static const char digits[] = "0123456789abcdef";
    char output[18];
    int index;
    output[0] = '0';
    output[1] = 'x';
    for (index = 0; index < 16; ++index) {
        output[index + 2] = digits[(value >> ((15 - index) * 4)) & 0x0fu];
    }
    typephp_os_write(output, sizeof(output));
}

static const char *exception_name(uint64_t vector)
{
    switch (vector) {
    case 0: return "divide error";
    case 3: return "breakpoint";
    case 5: return "bounds";
    case 6: return "invalid opcode";
    case 10: return "invalid TSS";
    case 11: return "segment not present";
    case 12: return "stack fault";
    case 13: return "general protection";
    case 14: return "page fault";
    default: return "unknown";
    }
}

static void restore_shell_after_fault(exception_frame *frame)
{
    uint64_t child_address_space = foreground_process.address_space;
    syscall_frame *parent = &foreground_process.parent_frame;
    /* The general-register prefixes of both frame formats are identical. */
    typephp_socket_close_owner(foreground_process.pid);
    memcpy(frame, parent, offsetof(syscall_frame, rip));
    frame->rax = (uint64_t) -EIO;
    frame->rip = parent->rip;
    frame->cs = parent->cs;
    frame->rflags = parent->rflags;
    frame->rsp = parent->rsp;
    frame->ss = parent->ss;
    foreground_process.address_space = foreground_process.parent_address_space;
    foreground_process.program_break = foreground_process.parent_program_break;
    foreground_process.minimum_break = foreground_process.parent_minimum_break;
    foreground_process.mmap_cursor = foreground_process.parent_mmap_cursor;
    foreground_process.mmap_limit = foreground_process.parent_mmap_limit;
    foreground_process.parent_waiting = 0;
    foreground_process.pid = 1;
    typephp_vm_activate(foreground_process.address_space);
    typephp_vm_destroy(child_address_space);
    if (physical_page_available() != foreground_process.parent_free_pages) {
        panic("faulted address-space page leak\n");
    }
}

void typephp_os_exception_dispatch(exception_frame *frame)
{
    uint64_t fault_address = 0;
    if (frame->vector == 14) {
        __asm__ volatile("mov %%cr2, %0" : "=r"(fault_address));
    }

    typephp_os_write("User process ", sizeof("User process ") - 1);
    write_unsigned(foreground_process.pid);
    typephp_os_write(" fault: ", sizeof(" fault: ") - 1);
    typephp_os_write(exception_name(frame->vector), strlen(exception_name(frame->vector)));
    typephp_os_write(" (#", sizeof(" (#") - 1);
    write_unsigned(frame->vector);
    typephp_os_write(") at ", sizeof(") at ") - 1);
    write_hex(frame->rip);
    if (frame->vector == 14) {
        typephp_os_write(", address ", sizeof(", address ") - 1);
        write_hex(fault_address);
    }
    typephp_os_write("\n", 1);

    if ((frame->cs & 3u) != 3u) {
        panic("kernel-mode exception\n");
    }
    if (!foreground_process.parent_waiting) {
        panic("resident shell faulted\n");
    }
    restore_shell_after_fault(frame);
}

void typephp_os_irq_dispatch(exception_frame *frame)
{
    switch (frame->vector) {
    case 32:
        ++timer_ticks;
        pic_end_of_interrupt(0);
        return;
    case 36:
        typephp_os_console_interrupt();
        pic_end_of_interrupt(4);
        return;
    default:
        panic("unexpected hardware interrupt\n");
    }
}

long typephp_os_syscall_dispatch(syscall_frame *frame)
{
    switch (frame->rax) {
    case TYPEPHP_SYS_READ:
        if (!user_buffer((void *) frame->rsi, frame->rdx)) {
            return -EFAULT;
        }
        if (frame->rdi == STDIN_FILENO) {
            return typephp_os_console_read((void *) frame->rsi, frame->rdx);
        }
        if (frame->rdi <= STDERR_FILENO) {
            return -EBADF;
        }
        if (typephp_socket_is_fd((int) frame->rdi)) {
            return typephp_socket_read(
                (int) frame->rdi, (void *) frame->rsi, frame->rdx);
        }
        return posix_syscall_result(read(
            (int) frame->rdi, (void *) frame->rsi, frame->rdx));
    case TYPEPHP_SYS_WRITE:
        if (!user_buffer((void *) frame->rsi, frame->rdx)) {
            return -EFAULT;
        }
        if (frame->rdi == STDOUT_FILENO || frame->rdi == STDERR_FILENO) {
            typephp_os_write((const char *) frame->rsi, frame->rdx);
            return (long) frame->rdx;
        }
        if (frame->rdi == STDIN_FILENO) {
            return -EBADF;
        }
        if (typephp_socket_is_fd((int) frame->rdi)) {
            return typephp_socket_write(
                (int) frame->rdi, (const void *) frame->rsi, frame->rdx);
        }
        return posix_syscall_result(write(
            (int) frame->rdi, (const void *) frame->rsi, frame->rdx));
    case TYPEPHP_SYS_CLOSE:
        if (typephp_socket_is_fd((int) frame->rdi)) {
            return typephp_socket_close((int) frame->rdi);
        }
        return posix_syscall_result(close((int) frame->rdi));
    case TYPEPHP_SYS_STAT:
        return syscall_stat_path((const char *) frame->rdi,
            (struct stat *) frame->rsi, 0);
    case TYPEPHP_SYS_FSTAT:
        return syscall_fstat((int) frame->rdi, (struct stat *) frame->rsi);
    case TYPEPHP_SYS_LSTAT:
        return syscall_stat_path((const char *) frame->rdi,
            (struct stat *) frame->rsi, 1);
    case TYPEPHP_SYS_LSEEK:
        return posix_syscall_result(lseek(
            (int) frame->rdi, (off_t) frame->rsi, (int) frame->rdx));
    case TYPEPHP_SYS_MMAP:
        return syscall_mmap(frame->rdi, frame->rsi, (int) frame->rdx,
            (int) frame->r10, (int) frame->r8, frame->r9);
    case TYPEPHP_SYS_MPROTECT:
        return syscall_mprotect(frame->rdi, frame->rsi, (int) frame->rdx);
    case TYPEPHP_SYS_MUNMAP:
        return syscall_munmap(frame->rdi, frame->rsi);
    case TYPEPHP_SYS_BRK:
        return syscall_brk(frame->rdi);
    case TYPEPHP_SYS_IOCTL:
        return syscall_ioctl((int) frame->rdi, frame->rsi,
            (void *) frame->rdx);
    case TYPEPHP_SYS_ACCESS:
        return syscall_access_path(AT_FDCWD, (const char *) frame->rdi,
            (int) frame->rsi, 0);
    case TYPEPHP_SYS_NANOSLEEP:
        return syscall_nanosleep((const user_timespec *) frame->rdi,
            (user_timespec *) frame->rsi);
    case TYPEPHP_SYS_GETPID:
    case TYPEPHP_SYS_GETTID:
        return (long) foreground_process.pid;
    case TYPEPHP_SYS_FCNTL:
        if (typephp_socket_is_fd((int) frame->rdi)) {
            return typephp_socket_fcntl(
                (int) frame->rdi, (int) frame->rsi, (long) frame->rdx);
        }
        return posix_syscall_result(fcntl(
            (int) frame->rdi, (int) frame->rsi, (int) frame->rdx));
    case TYPEPHP_SYS_SOCKET:
        return typephp_socket_open(foreground_process.pid,
            (int) frame->rdi, (int) frame->rsi, (int) frame->rdx);
    case TYPEPHP_SYS_CONNECT:
        if (!user_buffer((const void *) frame->rsi, frame->rdx)) {
            return -EFAULT;
        }
        return typephp_socket_connect((int) frame->rdi,
            (const void *) frame->rsi, (typephp_socklen_t) frame->rdx);
    case TYPEPHP_SYS_BIND:
        if (!user_buffer((const void *) frame->rsi, frame->rdx)) {
            return -EFAULT;
        }
        return typephp_socket_bind((int) frame->rdi,
            (const void *) frame->rsi, (typephp_socklen_t) frame->rdx);
    case TYPEPHP_SYS_SENDTO:
        if (!user_buffer((const void *) frame->rsi, frame->rdx)
            || (frame->r8 != 0
                && !user_buffer((const void *) frame->r8, frame->r9))) {
            return -EFAULT;
        }
        return typephp_socket_sendto((int) frame->rdi,
            (const void *) frame->rsi, frame->rdx, (int) frame->r10,
            (const void *) frame->r8, (typephp_socklen_t) frame->r9);
    case TYPEPHP_SYS_RECVFROM:
        if (!user_buffer((void *) frame->rsi, frame->rdx)
            || (frame->r8 != 0
                && (!user_buffer((void *) frame->r8, sizeof(uint16_t))
                    || frame->r9 == 0
                    || !user_buffer((void *) frame->r9,
                        sizeof(typephp_socklen_t))))) {
            return -EFAULT;
        }
        return typephp_socket_recvfrom((int) frame->rdi,
            (void *) frame->rsi, frame->rdx, (int) frame->r10,
            (void *) frame->r8, (typephp_socklen_t *) frame->r9);
    case TYPEPHP_SYS_SHUTDOWN:
        return typephp_socket_shutdown((int) frame->rdi, (int) frame->rsi);
    case TYPEPHP_SYS_GETSOCKNAME:
    case TYPEPHP_SYS_GETPEERNAME:
        if (frame->rdx == 0
            || !user_buffer((void *) frame->rdx, sizeof(typephp_socklen_t))) {
            return -EFAULT;
        }
        if (frame->rsi != 0
            && !user_buffer((void *) frame->rsi,
                *(typephp_socklen_t *) frame->rdx)) {
            return -EFAULT;
        }
        return typephp_socket_getname((int) frame->rdi,
            (void *) frame->rsi, (typephp_socklen_t *) frame->rdx,
            frame->rax == TYPEPHP_SYS_GETPEERNAME);
    case TYPEPHP_SYS_SETSOCKOPT:
        if (!user_buffer((const void *) frame->r10, frame->r8)) {
            return -EFAULT;
        }
        return typephp_socket_setsockopt((int) frame->rdi,
            (int) frame->rsi, (int) frame->rdx,
            (const void *) frame->r10, (typephp_socklen_t) frame->r8);
    case TYPEPHP_SYS_GETSOCKOPT:
        if (frame->r8 == 0
            || !user_buffer((void *) frame->r8, sizeof(typephp_socklen_t))) {
            return -EFAULT;
        }
        if (!user_buffer((void *) frame->r10,
            *(typephp_socklen_t *) frame->r8)) {
            return -EFAULT;
        }
        return typephp_socket_getsockopt((int) frame->rdi,
            (int) frame->rsi, (int) frame->rdx,
            (void *) frame->r10, (typephp_socklen_t *) frame->r8);
    case TYPEPHP_SYS_POLL:
        if (frame->rsi > 1024
            || !user_buffer((void *) frame->rdi,
                frame->rsi * sizeof(typephp_pollfd))) {
            return -EFAULT;
        }
        return typephp_socket_poll((typephp_pollfd *) frame->rdi,
            frame->rsi, (int) frame->rdx);
    case TYPEPHP_SYS_SPAWN:
        return syscall_spawn(frame, (const char *const *) frame->rdi);
    case TYPEPHP_SYS_GETCWD:
        return syscall_getcwd((char *) frame->rdi, frame->rsi);
    case TYPEPHP_SYS_CHDIR:
        return syscall_chdir((const char *) frame->rdi);
    case TYPEPHP_SYS_MKDIR: {
        char resolved[USER_PATH_MAX];
        if (!resolved_path((const char *) frame->rdi, resolved, sizeof(resolved))) {
            return -EFAULT;
        }
        return posix_syscall_result(mkdir(resolved, (mode_t) frame->rsi));
    }
    case TYPEPHP_SYS_RMDIR: {
        char resolved[USER_PATH_MAX];
        if (!resolved_path((const char *) frame->rdi, resolved, sizeof(resolved))) {
            return -EFAULT;
        }
        return posix_syscall_result(rmdir(resolved));
    }
    case TYPEPHP_SYS_UNLINK: {
        char resolved[USER_PATH_MAX];
        if (!resolved_path((const char *) frame->rdi, resolved, sizeof(resolved))) {
            return -EFAULT;
        }
        return posix_syscall_result(unlink(resolved));
    }
    case TYPEPHP_SYS_FSYNC:
    case TYPEPHP_SYS_FDATASYNC:
        return posix_syscall_result(fsync((int) frame->rdi));
    case TYPEPHP_SYS_TRUNCATE:
        return syscall_truncate((const char *) frame->rdi, (off_t) frame->rsi);
    case TYPEPHP_SYS_FTRUNCATE:
        if ((off_t) frame->rsi < 0) {
            return -EINVAL;
        }
        return posix_syscall_result(ftruncate(
            (int) frame->rdi, (off_t) frame->rsi));
    case TYPEPHP_SYS_RENAME: {
        char old_path[USER_PATH_MAX];
        char new_path[USER_PATH_MAX];
        if (!resolved_path((const char *) frame->rdi, old_path, sizeof(old_path))
            || !resolved_path((const char *) frame->rsi, new_path, sizeof(new_path))) {
            return -EFAULT;
        }
        return posix_syscall_result(rename(old_path, new_path));
    }
    case TYPEPHP_SYS_TIME:
        return syscall_time((long *) frame->rdi);
    case TYPEPHP_SYS_GETDENTS64:
        if (frame->rdx == 0) {
            return -EINVAL;
        }
        if (!user_buffer((void *) frame->rsi, frame->rdx)) {
            return -EFAULT;
        }
        return typephp_os_fs_getdents64((int) frame->rdi,
            (void *) frame->rsi, frame->rdx);
    case TYPEPHP_SYS_GETTIMEOFDAY:
        if (frame->rsi != 0) {
            return -EINVAL;
        }
        return syscall_gettimeofday((user_timeval *) frame->rdi);
    case TYPEPHP_SYS_GETUID:
    case TYPEPHP_SYS_GETGID:
    case TYPEPHP_SYS_GETEUID:
    case TYPEPHP_SYS_GETEGID:
        return 0;
    case TYPEPHP_SYS_GETPPID:
        return foreground_process.parent_waiting ? 1 : 0;
    case TYPEPHP_SYS_CLOCK_GETTIME:
        return syscall_clock((int) frame->rdi,
            (user_timespec *) frame->rsi, 0);
    case TYPEPHP_SYS_CLOCK_GETRES:
        return syscall_clock((int) frame->rdi,
            (user_timespec *) frame->rsi, 1);
    case TYPEPHP_SYS_OPENAT:
        return syscall_openat((long) frame->rdi, (const char *) frame->rsi,
            (int) frame->rdx, (int) frame->r10);
    case TYPEPHP_SYS_NEWFSTATAT:
        return syscall_newfstatat((int) frame->rdi,
            (const char *) frame->rsi, (struct stat *) frame->rdx,
            (int) frame->r10);
    case TYPEPHP_SYS_FACCESSAT:
        return syscall_access_path((int) frame->rdi,
            (const char *) frame->rsi, (int) frame->rdx,
            (int) frame->r10);
    case TYPEPHP_SYS_GETRANDOM:
        return syscall_getrandom((void *) frame->rdi, frame->rsi,
            (unsigned int) frame->rdx);
    case TYPEPHP_SYS_DNS_RESOLVE_IPV4: {
        char hostname[256];
        size_t length;
        if (!user_buffer((void *) frame->rsi, sizeof(uint32_t))) {
            return -EFAULT;
        }
        length = bounded_user_string((const char *) frame->rdi,
            sizeof(hostname));
        if (length == (size_t) -1) {
            return -EFAULT;
        }
        memcpy(hostname, (const void *) frame->rdi, length + 1u);
        return typephp_network_resolve_ipv4(hostname,
            (uint32_t *) frame->rsi);
    }
    case TYPEPHP_SYS_EXIT:
    case TYPEPHP_SYS_EXIT_GROUP:
        return syscall_exit(frame, (long) frame->rdi);
    case TYPEPHP_SYS_UNAME:
        return syscall_uname((user_utsname *) frame->rdi);
    case TYPEPHP_SYS_MEMORY_INFO:
        return syscall_memory_info((typephp_os_memory_info *) frame->rdi);
    default:
        return -ENOSYS;
    }
}

void typephp_os_process_start(void)
{
    uint64_t entry;
    uint64_t address_space;
    uint64_t image_end;
    static const char *const shell_arguments[] = {"sh"};
    address_space = typephp_vm_create();
    if (address_space == 0
        || !typephp_vm_map_user(address_space,
            USER_STACK + 16u - USER_STACK_SIZE,
            USER_STACK_SIZE, TYPEPHP_VM_USER_WRITE)) {
        panic("unable to create shell address space\n");
    }
    typephp_vm_activate(address_space);
    if (load_user_elf_file("/BIN/SH.ELF", address_space,
        USER_BEGIN, USER_SHELL_END, &entry, &image_end) < 0) {
        panic("unable to load /BIN/SH.ELF\n");
    }
    install_descriptor_tables();
    if (typephp_network_init()) {
        typephp_os_write("Network: e1000/lwIP IPv4 ready\n",
            sizeof("Network: e1000/lwIP IPv4 ready\n") - 1);
    } else {
        typephp_os_write("Network: no supported adapter\n",
            sizeof("Network: no supported adapter\n") - 1);
    }
    foreground_process.running = 1;
    foreground_process.address_space = address_space;
    foreground_process.minimum_break = image_end;
    foreground_process.program_break = image_end;
    foreground_process.mmap_limit = page_align_down(
        USER_STACK + 16u - USER_STACK_SIZE);
    foreground_process.mmap_cursor = foreground_process.mmap_limit;
    typephp_os_write("Process 1: sh.elf (Ring 3)\n",
        sizeof("Process 1: sh.elf (Ring 3)\n") - 1);
    typephp_os_enter_user(entry,
        prepare_initial_stack(USER_STACK, 1, shell_arguments));
    panic("Ring-3 entry returned\n");
}
