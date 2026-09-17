#include <sys/stat.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    if (argc != 2) {
        (void) write(STDERR_FILENO, "usage: mkdir DIRECTORY\n",
            sizeof("usage: mkdir DIRECTORY\n") - 1);
        return 1;
    }
    if (mkdir(argv[1], 0777) != 0) {
        (void) write(STDERR_FILENO, "mkdir: cannot create directory\n",
            sizeof("mkdir: cannot create directory\n") - 1);
        return 1;
    }
    return 0;
}
