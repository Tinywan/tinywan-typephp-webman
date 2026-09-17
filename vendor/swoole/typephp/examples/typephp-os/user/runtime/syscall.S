.section .text
.code64

.global syscall
.type syscall, @function
.extern __errno_location
syscall:
    # Translate the System V variadic-call ABI to Linux x86-64 syscall
    # registers, then use the native 64-bit fast-system-call instruction.
    mov %rdi, %rax
    mov %rsi, %rdi
    mov %rdx, %rsi
    mov %rcx, %rdx
    mov %r8, %r10
    mov %r9, %r8
    mov 8(%rsp), %r9
    syscall
    cmp $-4095, %rax
    jb 1f
    neg %eax
    push %rax
    call __errno_location
    pop %rdx
    mov %edx, (%rax)
    mov $-1, %rax
1:
    ret
.size syscall, . - syscall

.section .note.GNU-stack, "", @progbits
