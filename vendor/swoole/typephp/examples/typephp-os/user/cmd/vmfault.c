#include <stdint.h>

int main(void)
{
    /* The resident shell is linked at 1 GiB. A command has a distinct CR3,
     * so this address must be unmapped even though the shell remains alive. */
    volatile const uint8_t *shell_memory = (const uint8_t *) UINT64_C(0x40000000);
    return *shell_memory;
}
