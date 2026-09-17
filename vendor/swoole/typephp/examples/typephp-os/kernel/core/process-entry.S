.section .text
.code64

.global typephp_os_syscall_entry
.type typephp_os_syscall_entry, @function
.extern typephp_os_syscall_dispatch
.extern typephp_os_syscall_stack
.extern typephp_os_syscall_user_rsp
typephp_os_syscall_entry:
    # SYSCALL leaves RSP unchanged, stores the user RIP in RCX and stores the
    # user RFLAGS in R11. Save the untrusted user stack pointer before moving
    # to the kernel-owned syscall stack, then construct the same IRET frame
    # used by exceptions and the former interrupt-gate entry.
    mov %rsp, typephp_os_syscall_user_rsp(%rip)
    lea typephp_os_syscall_stack+65536(%rip), %rsp
    pushq $0x1b
    pushq typephp_os_syscall_user_rsp(%rip)
    push %r11
    pushq $0x23
    push %rcx

    # Save every general-purpose register. The saved RAX slot is overwritten
    # with the syscall result before the frame is restored.
    push %rbx
    push %rcx
    push %rdx
    push %rsi
    push %rdi
    push %rbp
    push %r8
    push %r9
    push %r10
    push %r11
    push %r12
    push %r13
    push %r14
    push %r15
    push %rax

    mov %rsp, %rdi
    call typephp_os_syscall_dispatch
    mov %rax, (%rsp)

    pop %rax
    pop %r15
    pop %r14
    pop %r13
    pop %r12
    pop %r11
    pop %r10
    pop %r9
    pop %r8
    pop %rbp
    pop %rdi
    pop %rsi
    pop %rdx
    pop %rcx
    pop %rbx
    iretq
.size typephp_os_syscall_entry, . - typephp_os_syscall_entry

.macro TYPEPHP_EXCEPTION_NO_ERROR number
.global typephp_os_exception_\number
.type typephp_os_exception_\number, @function
typephp_os_exception_\number:
    pushq $0
    pushq $\number
    jmp typephp_os_exception_common
.size typephp_os_exception_\number, . - typephp_os_exception_\number
.endm

.macro TYPEPHP_EXCEPTION_WITH_ERROR number
.global typephp_os_exception_\number
.type typephp_os_exception_\number, @function
typephp_os_exception_\number:
    # The CPU has already pushed the exception error code.
    pushq $\number
    jmp typephp_os_exception_common
.size typephp_os_exception_\number, . - typephp_os_exception_\number
.endm

TYPEPHP_EXCEPTION_NO_ERROR 0
TYPEPHP_EXCEPTION_NO_ERROR 3
TYPEPHP_EXCEPTION_NO_ERROR 5
TYPEPHP_EXCEPTION_NO_ERROR 6
TYPEPHP_EXCEPTION_WITH_ERROR 10
TYPEPHP_EXCEPTION_WITH_ERROR 11
TYPEPHP_EXCEPTION_WITH_ERROR 12
TYPEPHP_EXCEPTION_WITH_ERROR 13
TYPEPHP_EXCEPTION_WITH_ERROR 14

.macro TYPEPHP_IRQ number vector
.global typephp_os_irq_\number
.type typephp_os_irq_\number, @function
typephp_os_irq_\number:
    pushq $0
    pushq $\vector
    jmp typephp_os_irq_common
.size typephp_os_irq_\number, . - typephp_os_irq_\number
.endm

TYPEPHP_IRQ 0 32
TYPEPHP_IRQ 4 36

.type typephp_os_irq_common, @function
.extern typephp_os_irq_dispatch
typephp_os_irq_common:
    push %rbx
    push %rcx
    push %rdx
    push %rsi
    push %rdi
    push %rbp
    push %r8
    push %r9
    push %r10
    push %r11
    push %r12
    push %r13
    push %r14
    push %r15
    push %rax

    mov %rsp, %rdi
    call typephp_os_irq_dispatch

    pop %rax
    pop %r15
    pop %r14
    pop %r13
    pop %r12
    pop %r11
    pop %r10
    pop %r9
    pop %r8
    pop %rbp
    pop %rdi
    pop %rsi
    pop %rdx
    pop %rcx
    pop %rbx
    add $16, %rsp
    iretq
.size typephp_os_irq_common, . - typephp_os_irq_common

.type typephp_os_exception_common, @function
.extern typephp_os_exception_dispatch
typephp_os_exception_common:
    push %rbx
    push %rcx
    push %rdx
    push %rsi
    push %rdi
    push %rbp
    push %r8
    push %r9
    push %r10
    push %r11
    push %r12
    push %r13
    push %r14
    push %r15
    push %rax

    mov %rsp, %rdi
    call typephp_os_exception_dispatch

    pop %rax
    pop %r15
    pop %r14
    pop %r13
    pop %r12
    pop %r11
    pop %r10
    pop %r9
    pop %r8
    pop %rbp
    pop %rdi
    pop %rsi
    pop %rdx
    pop %rcx
    pop %rbx
    add $16, %rsp
    iretq
.size typephp_os_exception_common, . - typephp_os_exception_common

.global typephp_os_enter_user
.type typephp_os_enter_user, @function
typephp_os_enter_user:
    # RDI = ELF entry, RSI = top of the user stack. Hardware interrupts stay
    # enabled in Ring 3 so timer and serial input can arrive while a command is
    # running. IA32_FMASK masks them again on every SYSCALL entry.
    mov $0x1b, %ax
    mov %ax, %ds
    mov %ax, %es
    pushq $0x1b
    push %rsi
    pushq $0x202
    pushq $0x23
    push %rdi
    iretq
.size typephp_os_enter_user, . - typephp_os_enter_user

.section .note.GNU-stack, "", @progbits
