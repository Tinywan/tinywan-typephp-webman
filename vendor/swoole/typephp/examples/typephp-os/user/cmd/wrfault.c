#include <stdint.h>
#include <sys/mman.h>

int main(void)
{
    volatile uint8_t *memory = mmap(NULL, 4096, PROT_READ,
        MAP_PRIVATE | MAP_ANONYMOUS, -1, 0);
    if (memory == MAP_FAILED) {
        return 1;
    }

    /* CR0.WP and the final user PTE permissions must turn this into a
     * protection fault rather than silently modifying a read-only page. */
    memory[0] = 0x5a;
    return 1;
}
