#include <stdint.h>
#include <stddef.h>
#include <sys/syscall.h>

#include <typephp_os_abi.h>

long syscall(long number, ...);
int main(int argc, char **argv);

/* Small C utilities only need a modest libc arena. A heavier runtime can
 * override this weak definition without giving C and TypePHP programs
 * separate crt0 implementations. */
__attribute__((weak, noinline)) size_t typephp_os_runtime_arena_size(void)
{
    return 2u * 1024u * 1024u;
}

int typephp_os_runtime_start(int argc, char **argv)
{
    const size_t arena_size = typephp_os_runtime_arena_size();
    const uintptr_t begin = (uintptr_t) syscall(SYS_brk, 0);
    const uintptr_t end = begin + arena_size;
    if (begin == UINTPTR_MAX || end < begin
        || (uintptr_t) syscall(SYS_brk, end) != end) {
        typephp_os_panic("unable to allocate the userspace runtime arena");
    }
    typephp_os_memory_init((void *) begin, arena_size);
    return main(argc, argv);
}
