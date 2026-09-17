#include <stdarg.h>
#include <sys/ioctl.h>
#include <sys/syscall.h>

long syscall(long number, ...);

int ioctl(int fd, unsigned long request, ...)
{
    void *argument = 0;

    /* TIOCNOTTY has no third argument. The currently supported window-size
     * requests use a pointer, as required by the Linux userspace ABI. */
    if (request != TIOCNOTTY) {
        va_list arguments;
        va_start(arguments, request);
        argument = va_arg(arguments, void *);
        va_end(arguments);
    }
    return (int) syscall(SYS_ioctl, fd, request, argument);
}
