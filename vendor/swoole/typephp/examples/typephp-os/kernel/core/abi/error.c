#include <errno.h>
#include <stddef.h>
#include <stdio.h>
#include <string.h>
#include <unistd.h>

static int typephp_os_errno;
static char unknown_error[] = "Unknown error";

int *__errno_location(void)
{
    return &typephp_os_errno;
}

char *strerror(int error)
{
    switch (error) {
    case ENOENT: return "No such file or directory";
    case EIO: return "Input/output error";
    case ENOEXEC: return "Exec format error";
    case EBADF: return "Bad file descriptor";
    case ENOMEM: return "Cannot allocate memory";
    case EACCES: return "Permission denied";
    case EFAULT: return "Bad address";
    case EEXIST: return "File exists";
    case ENOTDIR: return "Not a directory";
    case EISDIR: return "Is a directory";
    case EINVAL: return "Invalid argument";
    case ENOTTY: return "Inappropriate ioctl for device";
    case EMFILE: return "Too many open files";
    case EROFS: return "Read-only file system";
    case ERANGE: return "Numerical result out of range";
    case ENAMETOOLONG: return "File name too long";
    case ENOSYS: return "Function not implemented";
    case ENOTEMPTY: return "Directory not empty";
    default: return unknown_error;
    }
}

void perror(const char *prefix)
{
    if (prefix != NULL && prefix[0] != '\0') {
        (void) write(STDERR_FILENO, prefix, strlen(prefix));
        (void) write(STDERR_FILENO, ": ", 2);
    }
    (void) write(STDERR_FILENO, strerror(errno), strlen(strerror(errno)));
    (void) write(STDERR_FILENO, "\n", 1);
}
