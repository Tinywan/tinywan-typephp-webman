.set MULTIBOOT_MAGIC, 0x1badb002
.set MULTIBOOT_FLAGS, 0x00000003
.set MULTIBOOT_CHECKSUM, -(MULTIBOOT_MAGIC + MULTIBOOT_FLAGS)

.section .multiboot, "a"
.align 4
.long MULTIBOOT_MAGIC
.long MULTIBOOT_FLAGS
.long MULTIBOOT_CHECKSUM

.section .bss
.align 4096
pml4_table:
.skip 4096
pdp_table:
.skip 4096
pd_table:
.skip 4096

.align 16
stack_bottom:
/* PHP Nano and the TypePHP startup self-check use more than the original
 * 16 KiB bootstrap stack before Ring-3 is entered. Keep enough headroom that
 * downward growth cannot overwrite the adjacent identity-map page tables. */
.skip 262144
stack_top:

.section .text
.code32
.global _start
.type _start, @function
.extern kernel_entry
_start:
    cli
    mov $stack_top, %esp

    # Preserve the Multiboot registers while page tables and MSRs are set up.
    mov %eax, %ebp

    # Identity-map the first GiB with 2 MiB pages.
    mov $pdp_table, %eax
    # The bootstrap map is kernel-only. Each process later receives a
    # separate CR3 with a high userspace region.
    or $0x3, %eax
    mov %eax, pml4_table

    mov $pd_table, %eax
    or $0x3, %eax
    mov %eax, pdp_table

    mov $pd_table, %edi
    mov $0x83, %eax
    mov $512, %ecx
map_page:
    mov %eax, (%edi)
    add $0x200000, %eax
    add $8, %edi
    loop map_page

    # Enable PAE and prepare the x87/SSE state before entering long mode.
    mov %cr0, %ecx
    and $0xfffffffb, %ecx
    or $0x2, %ecx
    mov %ecx, %cr0
    mov %cr4, %ecx
    or $0x620, %ecx
    mov %ecx, %cr4
    fninit

    mov $pml4_table, %eax
    mov %eax, %cr3

    # Enable long mode and execute-disable page permissions.
    mov $0xc0000080, %ecx
    rdmsr
    or $0x900, %eax
    wrmsr

    mov %cr0, %eax
    or $0x80010000, %eax
    mov %eax, %cr0

    lgdt gdt64_pointer

    # The 64-bit System V ABI receives Multiboot magic/info in RDI/RSI.
    mov %ebp, %edi
    mov %ebx, %esi
    ljmp $0x08, $long_mode_start

.code64
long_mode_start:
    mov $0x10, %ax
    mov %ax, %ds
    mov %ax, %es
    mov %ax, %ss
    xor %ax, %ax
    mov %ax, %fs
    mov %ax, %gs
    and $-16, %rsp

    # The embedded ELF64 payload is linked and loaded at exactly 2 MiB.
    movabs $0x200000, %rax
    call *%rax

halt_forever:
    cli
    hlt
    jmp halt_forever
.size _start, . - _start

.section .rodata
.align 8
gdt64:
    .quad 0x0000000000000000
    .quad 0x00af9a000000ffff
    .quad 0x00cf92000000ffff
gdt64_end:

gdt64_pointer:
    .word gdt64_end - gdt64 - 1
    .long gdt64

.section .note.GNU-stack, "", @progbits
