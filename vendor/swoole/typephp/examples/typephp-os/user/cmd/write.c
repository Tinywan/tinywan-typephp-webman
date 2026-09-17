#include <fcntl.h>
#include <stdio.h>
#include <string.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    int fd;
    if (argc < 3) {
        (void) write(STDERR_FILENO, "usage: write FILE TEXT...\n",
            sizeof("usage: write FILE TEXT...\n") - 1);
        return 1;
    }
    fd = open(argv[1], O_WRONLY | O_CREAT | O_TRUNC, 0666);
    if (fd < 0) {
        perror("write");
        return 1;
    }
    for (int index = 2; index < argc; ++index) {
        if (index != 2 && write(fd, " ", 1) != 1) {
            (void) close(fd);
            return 1;
        }
        if (write(fd, argv[index], strlen(argv[index])) < 0) {
            (void) close(fd);
            return 1;
        }
    }
    if (write(fd, "\n", 1) != 1 || close(fd) != 0) {
        return 1;
    }
    return 0;
}
