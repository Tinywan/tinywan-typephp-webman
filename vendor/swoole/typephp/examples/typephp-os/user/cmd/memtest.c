#include <stddef.h>
#include <stdio.h>
#include <sys/mman.h>
#include <unistd.h>

static int zeroed(const unsigned char *memory, size_t size)
{
    for (size_t index = 0; index < size; ++index) {
        if (memory[index] != 0) {
            return 0;
        }
    }
    return 1;
}

int main(void)
{
    unsigned char *heap = sbrk(8192);
    if (heap == (void *) -1 || !zeroed(heap, 8192)) {
        perror("memtest: sbrk");
        return 1;
    }
    heap[0] = 0x12;
    heap[8191] = 0x34;
    if (heap[0] != 0x12 || heap[8191] != 0x34 || sbrk(-8192) == (void *) -1) {
        return 1;
    }

    unsigned char *mapping = mmap(NULL, 8192, PROT_READ | PROT_WRITE,
        MAP_PRIVATE | MAP_ANONYMOUS, -1, 0);
    if (mapping == MAP_FAILED || !zeroed(mapping, 8192)) {
        perror("memtest: mmap");
        return 1;
    }
    mapping[0] = 0x56;
    mapping[4096] = 0x78;
    if (mprotect(mapping + 4096, 4096, PROT_NONE) != 0
        || mprotect(mapping + 4096, 4096, PROT_READ | PROT_WRITE) != 0
        || mapping[0] != 0x56 || mapping[4096] != 0x78
        || munmap(mapping, 8192) != 0) {
        perror("memtest: mapping");
        return 1;
    }
    (void) write(STDOUT_FILENO, "brk/mmap: OK\n", sizeof("brk/mmap: OK\n") - 1);
    return 0;
}
