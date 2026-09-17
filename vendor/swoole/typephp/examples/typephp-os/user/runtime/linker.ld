OUTPUT_FORMAT(elf64-x86-64)
OUTPUT_ARCH(i386:x86-64)
ENTRY(_start)

PHDRS
{
    text PT_LOAD FILEHDR PHDRS FLAGS(5);
    data PT_LOAD FLAGS(6);
    stack PT_GNU_STACK FLAGS(6);
}

SECTIONS
{
    . = USER_BASE + SIZEOF_HEADERS;

    .text :
    {
        KEEP(*(.text.start))
        *(.text .text.*)
    } :text
    .rodata : { *(.rodata .rodata.*) } :text

    . = ALIGN(4K);
    .data : { *(.data .data.*) } :data
    .bss (NOLOAD) : { *(COMMON) *(.bss .bss.*) } :data

    /DISCARD/ : { *(.comment) *(.eh_frame*) *(.note*) }
}
