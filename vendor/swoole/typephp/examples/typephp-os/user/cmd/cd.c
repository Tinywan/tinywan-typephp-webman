#include <unistd.h>

int main(int argc, char **argv)
{
    const char *path = argc > 1 ? argv[1] : "/";
    if (chdir(path) != 0) {
        (void) write(STDERR_FILENO, "cd: no such directory\n",
            sizeof("cd: no such directory\n") - 1);
        return 1;
    }
    return 0;
}
