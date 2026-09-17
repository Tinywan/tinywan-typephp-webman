#include <fcntl.h>
#include <stdio.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    int fd;
    if (argc != 2) {
        (void) write(STDERR_FILENO, "usage: touch FILE\n",
            sizeof("usage: touch FILE\n") - 1);
        return 1;
    }
    fd = open(argv[1], O_WRONLY | O_CREAT, 0666);
    if (fd < 0) {
        perror("touch");
        return 1;
    }
    return close(fd) == 0 ? 0 : 1;
}
