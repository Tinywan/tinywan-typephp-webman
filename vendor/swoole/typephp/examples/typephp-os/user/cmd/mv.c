#include <stdio.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    if (argc != 3) {
        (void) write(STDERR_FILENO, "usage: mv SOURCE DESTINATION\n",
            sizeof("usage: mv SOURCE DESTINATION\n") - 1);
        return 1;
    }
    if (rename(argv[1], argv[2]) != 0) {
        perror("mv");
        return 1;
    }
    return 0;
}
