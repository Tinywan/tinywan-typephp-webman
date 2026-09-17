OUTPUT_FORMAT(elf32-i386)
OUTPUT_ARCH(i386)
ENTRY(_start)

SECTIONS
{
    . = 1M;

    .multiboot ALIGN(4) :
    {
        KEEP(*(.multiboot))
    }

    .text ALIGN(4K) :
    {
        *(.text .text.*)
    }

    .rodata ALIGN(4K) :
    {
        *(.rodata .rodata.*)
    }

    .data ALIGN(4K) :
    {
        *(.data .data.*)
    }

    .bss ALIGN(4K) (NOLOAD) :
    {
        *(COMMON)
        *(.bss .bss.*)
    }

    ASSERT(. < 2M, "32-bit bootstrap overlaps the 64-bit payload")

    . = 2M;
    .payload ALIGN(4K) :
    {
        KEEP(*(.payload))
    }

    /DISCARD/ :
    {
        *(.comment)
        *(.eh_frame*)
        *(.note*)
    }
}
