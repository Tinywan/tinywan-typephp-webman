/* Development-only command used by the QEMU smoke test to verify that a
 * broken Ring-3 command is terminated without taking down the kernel. */
int main(int argc, char **argv)
{
    (void) argc;
    (void) argv;
    __asm__ volatile("ud2");
    return 1;
}
