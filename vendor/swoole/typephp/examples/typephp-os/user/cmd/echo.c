#include <string.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    for (int index = 1; index < argc; ++index) {
        if (index != 1) {
            (void) write(STDOUT_FILENO, " ", 1);
        }
        (void) write(STDOUT_FILENO, argv[index], strlen(argv[index]));
    }
    (void) write(STDOUT_FILENO, "\n", 1);
    return 0;
}
