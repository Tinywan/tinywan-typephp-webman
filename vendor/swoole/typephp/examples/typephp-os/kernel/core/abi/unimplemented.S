.section .text
.code64
.extern typephp_os_panic

/* ABI coverage comes before subsystem implementation. Every placeholder is a
 * real exported symbol so the complete php-nano/PHPX source set links, while
 * any accidental runtime use fails loudly instead of returning fake data. */
.macro TYPEPHP_OS_UNIMPLEMENTED symbol
    .global \symbol
    .weak \symbol
    .type \symbol, @function
\symbol:
    lea .Lmessage\@(%rip), %rdi
    jmp typephp_os_panic
    .pushsection .rodata
.Lmessage\@:
    .asciz "unimplemented libc/POSIX ABI: \symbol"
    .popsection
.endm

TYPEPHP_OS_UNIMPLEMENTED chmod
TYPEPHP_OS_UNIMPLEMENTED chown
TYPEPHP_OS_UNIMPLEMENTED accept
TYPEPHP_OS_UNIMPLEMENTED listen
TYPEPHP_OS_UNIMPLEMENTED fdopen
TYPEPHP_OS_UNIMPLEMENTED feof
TYPEPHP_OS_UNIMPLEMENTED fileno
TYPEPHP_OS_UNIMPLEMENTED flock
TYPEPHP_OS_UNIMPLEMENTED fopen
TYPEPHP_OS_UNIMPLEMENTED fclose
TYPEPHP_OS_UNIMPLEMENTED fread
TYPEPHP_OS_UNIMPLEMENTED fseek
TYPEPHP_OS_UNIMPLEMENTED ftell
TYPEPHP_OS_UNIMPLEMENTED getgrnam
TYPEPHP_OS_UNIMPLEMENTED getgroups
TYPEPHP_OS_UNIMPLEMENTED getpwnam
TYPEPHP_OS_UNIMPLEMENTED getpwnam_r
TYPEPHP_OS_UNIMPLEMENTED getpwuid_r
TYPEPHP_OS_UNIMPLEMENTED gnu_dev_major
TYPEPHP_OS_UNIMPLEMENTED gnu_dev_minor
TYPEPHP_OS_UNIMPLEMENTED mkstemp
TYPEPHP_OS_UNIMPLEMENTED readlink
TYPEPHP_OS_UNIMPLEMENTED rewind
TYPEPHP_OS_UNIMPLEMENTED umask
TYPEPHP_OS_UNIMPLEMENTED utime

TYPEPHP_OS_UNIMPLEMENTED mblen
TYPEPHP_OS_UNIMPLEMENTED localtime_r
TYPEPHP_OS_UNIMPLEMENTED strftime

TYPEPHP_OS_UNIMPLEMENTED _Unwind_Resume
TYPEPHP_OS_UNIMPLEMENTED __cxa_begin_catch
TYPEPHP_OS_UNIMPLEMENTED __cxa_end_catch
TYPEPHP_OS_UNIMPLEMENTED __cxa_rethrow
TYPEPHP_OS_UNIMPLEMENTED __gxx_personality_v0
TYPEPHP_OS_UNIMPLEMENTED _ZNSt15__exception_ptr13exception_ptr10_M_releaseEv

.section .note.GNU-stack, "", @progbits
