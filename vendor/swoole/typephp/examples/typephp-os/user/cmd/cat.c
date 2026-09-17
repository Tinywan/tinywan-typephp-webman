#include <fcntl.h>
#include <stdio.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    char buffer[256];
    ssize_t size;
    int fd;
    if (argc != 2) {
        (void) write(STDERR_FILENO, "usage: cat FILE\n", sizeof("usage: cat FILE\n") - 1);
        return 1;
    }
    fd = open(argv[1], O_RDONLY);
    if (fd < 0) {
        perror("cat");
        return 1;
    }
    while ((size = read(fd, buffer, sizeof(buffer))) > 0) {
        ssize_t offset = 0;
        while (offset < size) {
            ssize_t written = write(STDOUT_FILENO, buffer + offset,
                (size_t) (size - offset));
            if (written <= 0) {
                (void) close(fd);
                return 1;
            }
            offset += written;
        }
    }
    (void) close(fd);
    return size < 0 ? 1 : 0;
}
