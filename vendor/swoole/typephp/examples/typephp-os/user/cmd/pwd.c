#include <string.h>
#include <unistd.h>

int main(int argc, char **argv)
{
    char cwd[16];
    (void) argc;
    (void) argv;
    if (getcwd(cwd, sizeof(cwd)) == 0) {
        (void) write(STDERR_FILENO, "pwd: working directory unavailable\n",
            sizeof("pwd: working directory unavailable\n") - 1);
        return 1;
    }
    (void) write(STDOUT_FILENO, cwd, strlen(cwd));
    (void) write(STDOUT_FILENO, "\n", 1);
    return 0;
}
