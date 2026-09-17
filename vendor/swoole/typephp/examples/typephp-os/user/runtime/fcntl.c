#include <fcntl.h>
#include <stdarg.h>
#include <sys/syscall.h>

/* glibc hides this GNU extension when php-nano requests POSIX.1-2008 only. */
long syscall(long number, ...);

int fcntl(int fd, int command, ...)
{
    long argument = 0;
    if (command == F_SETFD || command == F_SETFL) {
        va_list arguments;
        va_start(arguments, command);
        argument = va_arg(arguments, int);
        va_end(arguments);
    }
    return (int) syscall(SYS_fcntl, fd, command, argument);
}
