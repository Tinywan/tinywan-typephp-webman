#include <dirent.h>
#include <stddef.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    const char *path = argc > 1 ? argv[1] : ".";
    DIR *directory = opendir(path);
    struct dirent *entry;
    if (directory == NULL) {
        (void) write(STDERR_FILENO, "ls: cannot read directory\n",
            sizeof("ls: cannot read directory\n") - 1);
        return 1;
    }
    while ((entry = readdir(directory)) != NULL) {
        size_t length = 0;
        while (entry->d_name[length] != '\0') {
            ++length;
        }
        (void) write(STDOUT_FILENO, entry->d_name, length);
        (void) write(STDOUT_FILENO, "\n", 1);
    }
    if (closedir(directory) != 0) {
        return 1;
    }
    return 0;
}
