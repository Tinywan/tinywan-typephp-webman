#include <stdio.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    if (argc != 2) {
        (void) write(STDERR_FILENO, "usage: rm FILE\n", sizeof("usage: rm FILE\n") - 1);
        return 1;
    }
    if (unlink(argv[1]) != 0) {
        perror("rm");
        return 1;
    }
    return 0;
}
