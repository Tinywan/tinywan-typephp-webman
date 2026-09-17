#include <errno.h>
#include <string.h>
#include <sys/syscall.h>
#include <unistd.h>

static size_t read_line(char *buffer, size_t capacity)
{
    size_t length = 0;
    while (capacity > 1) {
        char character = 0;
        if (read(STDIN_FILENO, &character, 1) != 1) {
            continue;
        }
        if (character == '\r' || character == '\n') {
            (void) write(STDOUT_FILENO, "\n", 1);
            break;
        }
        if (character == 8 || character == 127) {
            if (length != 0) {
                --length;
                (void) write(STDOUT_FILENO, "\b \b", 3);
            }
            continue;
        }
        if (character >= 32 && character < 127) {
            buffer[length++] = character;
            --capacity;
            (void) write(STDOUT_FILENO, &character, 1);
        }
    }
    buffer[length] = '\0';
    return length;
}

static void show_prompt(void)
{
    char cwd[128];
    (void) write(STDOUT_FILENO, "typephp-os:", sizeof("typephp-os:") - 1);
    if (getcwd(cwd, sizeof(cwd)) != 0) {
        (void) write(STDOUT_FILENO, cwd, strlen(cwd));
    } else {
        (void) write(STDOUT_FILENO, "?", 1);
    }
    (void) write(STDOUT_FILENO, "$ ", 2);
}

int main(int argc, char **argv)
{
    char line[80];
    unsigned short code_selector;
    (void) argc;
    (void) argv;
    __asm__ volatile("mov %%cs, %0" : "=r"(code_selector));
    if ((code_selector & 3u) != 3u) {
        (void) write(STDERR_FILENO, "sh: Ring-3 transition failed\n",
            sizeof("sh: Ring-3 transition failed\n") - 1);
        return 1;
    }
    (void) write(STDOUT_FILENO, "TypePHP-OS user shell\n",
        sizeof("TypePHP-OS user shell\n") - 1);
    (void) write(STDOUT_FILENO, "Ring 3 confirmed\n",
        sizeof("Ring 3 confirmed\n") - 1);
    (void) write(STDOUT_FILENO,
        "Commands: ls, cd, pwd, date, uname, free, cat, echo, write, touch, mkdir, rm, rmdir, mv, memtest, systest, nettest, builtins, tnhello, https, fault, vmfault, wrfault\n",
        sizeof("Commands: ls, cd, pwd, date, uname, free, cat, echo, write, touch, mkdir, rm, rmdir, mv, memtest, systest, nettest, builtins, tnhello, https, fault, vmfault, wrfault\n") - 1);
    for (;;) {
        char *arguments[9];
        int argument_count = 0;
        char *cursor;
        show_prompt();
        if (read_line(line, sizeof(line)) == 0) {
            continue;
        }
        cursor = line;
        while (*cursor != '\0' && argument_count < 8) {
            while (*cursor == ' ') {
                ++cursor;
            }
            if (*cursor == '\0') {
                break;
            }
            arguments[argument_count++] = cursor;
            while (*cursor != '\0' && *cursor != ' ') {
                ++cursor;
            }
            if (*cursor != '\0') {
                *cursor++ = '\0';
            }
        }
        arguments[argument_count] = 0;
        if (argument_count == 0) {
            continue;
        }
        if (syscall(SYS_typephp_spawn, arguments) < 0) {
            (void) write(STDERR_FILENO, "sh: ", sizeof("sh: ") - 1);
            (void) write(STDERR_FILENO, arguments[0], strlen(arguments[0]));
            (void) write(STDERR_FILENO, ": ", 2);
            (void) write(STDERR_FILENO, strerror(errno), strlen(strerror(errno)));
            (void) write(STDERR_FILENO, "\n", 1);
        }
    }
}
