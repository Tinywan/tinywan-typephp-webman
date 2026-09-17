#include <dirent.h>
#include <fcntl.h>
#include <stddef.h>
#include <errno.h>
#include <sys/ioctl.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <time.h>
#include <unistd.h>

_Static_assert(sizeof(struct stat) == 144, "Linux x86-64 stat ABI changed");

static int fail(const char *operation)
{
    size_t length = 0;
    while (operation[length] != '\0') {
        ++length;
    }
    (void) write(STDERR_FILENO, "systest: ", sizeof("systest: ") - 1);
    (void) write(STDERR_FILENO, operation, length);
    (void) write(STDERR_FILENO, " failed\n", sizeof(" failed\n") - 1);
    return 1;
}

static int same_string(const char *left, const char *right)
{
    while (*left != '\0' && *left == *right) {
        ++left;
        ++right;
    }
    return *left == *right;
}

int main(int argc, char **argv)
{
    static const char path[] = "/SYS.TMP";
    static const char payload[] = "abcdef";
    struct stat info;
    struct timeval wall;
    struct timespec realtime;
    struct timespec monotonic;
    struct timespec after_sleep;
    struct timespec resolution;
    const struct timespec sleep_duration = {0, 30000000};
    struct winsize window;
    DIR *directory;
    struct dirent *entry;
    int found_hello = 0;
    int fd;
    int socket_fd;
    (void) argc;
    (void) argv;

    if (stat("/HELLO.TXT", &info) != 0 || !S_ISREG(info.st_mode)
        || info.st_size <= 0 || access("/HELLO.TXT", R_OK) != 0) {
        return fail("stat/access");
    }
    if (fstatat(AT_FDCWD, "/HELLO.TXT", &info, AT_SYMLINK_NOFOLLOW) != 0
        || faccessat(AT_FDCWD, "/HELLO.TXT", R_OK, 0) != 0) {
        return fail("*at metadata");
    }

    (void) unlink(path);
    fd = open(path, O_CREAT | O_TRUNC | O_RDWR, 0666);
    if (fd < 0
        || write(fd, payload, sizeof(payload) - 1) != sizeof(payload) - 1) {
        return fail("descriptor write");
    }
    if ((fcntl(fd, F_GETFL) & 3) != O_RDWR
        || fcntl(fd, F_SETFD, FD_CLOEXEC) != 0
        || fcntl(fd, F_GETFD) != FD_CLOEXEC
        || fcntl(fd, F_SETFL, O_APPEND) != 0
        || lseek(fd, 0, SEEK_SET) != 0
        || write(fd, "Z", 1) != 1
        || fstat(fd, &info) != 0 || info.st_size != 7) {
        return fail("fcntl/append");
    }
    if (fsync(fd) != 0 || ftruncate(fd, 3) != 0
        || fstat(fd, &info) != 0 || info.st_size != 3
        || fdatasync(fd) != 0 || close(fd) != 0) {
        return fail("descriptor persistence");
    }
    if (truncate(path, 1) != 0 || lstat(path, &info) != 0
        || info.st_size != 1 || unlink(path) != 0) {
        return fail("path truncate");
    }

    directory = opendir("/");
    if (directory == NULL || fstat(dirfd(directory), &info) != 0
        || !S_ISDIR(info.st_mode)) {
        return fail("opendir/fstat");
    }
    while ((entry = readdir(directory)) != NULL) {
        if (same_string(entry->d_name, "HELLO.TXT")) {
            found_hello = 1;
        }
    }
    rewinddir(directory);
    entry = readdir(directory);
    if (!found_hello || entry == NULL || !same_string(entry->d_name, ".")
        || closedir(directory) != 0) {
        return fail("getdents64/rewinddir");
    }

    if (getpid() != 2 || gettid() != 2 || getppid() != 1
        || getuid() != 0 || geteuid() != 0 || getgid() != 0 || getegid() != 0) {
        return fail("single-task identity");
    }
    if (ioctl(STDOUT_FILENO, TIOCGWINSZ, &window) != 0
        || window.ws_row != 25 || window.ws_col != 80
        || window.ws_xpixel != 0 || window.ws_ypixel != 0
        || ioctl(STDOUT_FILENO, TIOCNOTTY) != -1 || errno != ENOTTY) {
        return fail("ioctl console ABI");
    }
    if (gettimeofday(&wall, NULL) != 0 || wall.tv_sec <= 0
        || clock_gettime(CLOCK_REALTIME, &realtime) != 0
        || clock_gettime(CLOCK_MONOTONIC, &monotonic) != 0
        || clock_getres(CLOCK_MONOTONIC, &resolution) != 0
        || realtime.tv_sec <= 0 || monotonic.tv_nsec < 0
        || monotonic.tv_nsec >= 1000000000L
        || resolution.tv_sec != 0 || resolution.tv_nsec != 10000000L) {
        return fail("clock ABI");
    }
    if (nanosleep(&sleep_duration, NULL) != 0
        || clock_gettime(CLOCK_MONOTONIC, &after_sleep) != 0
        || after_sleep.tv_sec < monotonic.tv_sec
        || (after_sleep.tv_sec == monotonic.tv_sec
            && after_sleep.tv_nsec - monotonic.tv_nsec < 20000000L)) {
        return fail("interruptible nanosleep");
    }

    if (socket(AF_INET, SOCK_RAW, 0) != -1 || errno != ESOCKTNOSUPPORT) {
        return fail("raw socket rejection");
    }
    socket_fd = socket(AF_INET,
        SOCK_STREAM | SOCK_NONBLOCK | SOCK_CLOEXEC, 0);
    if (socket_fd < 0
        || (fcntl(socket_fd, F_GETFL) & O_NONBLOCK) == 0
        || fcntl(socket_fd, F_GETFD) != FD_CLOEXEC
        || close(socket_fd) != 0) {
        return fail("TCP socket descriptor ABI");
    }

    (void) write(STDOUT_FILENO, "basic syscalls: OK\n",
        sizeof("basic syscalls: OK\n") - 1);
    return 0;
}
