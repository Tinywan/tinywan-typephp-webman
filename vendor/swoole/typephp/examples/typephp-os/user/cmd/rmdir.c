#include <stdio.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    if (argc != 2) {
        (void) write(STDERR_FILENO, "usage: rmdir DIRECTORY\n",
            sizeof("usage: rmdir DIRECTORY\n") - 1);
        return 1;
    }
    if (rmdir(argv[1]) != 0) {
        perror("rmdir");
        return 1;
    }
    return 0;
}
