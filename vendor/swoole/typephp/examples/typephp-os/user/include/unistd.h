#ifndef TYPEPHP_OS_USER_UNISTD_H
#define TYPEPHP_OS_USER_UNISTD_H

#include <stddef.h>
#include <stdint.h>
#include <sys/types.h>

#define STDIN_FILENO 0
#define STDOUT_FILENO 1
#define STDERR_FILENO 2

ssize_t read(int fd, void *buffer, size_t count);
ssize_t write(int fd, const void *buffer, size_t count);
int close(int fd);
off_t lseek(int fd, off_t offset, int whence);
int chdir(const char *path);
char *getcwd(char *buffer, size_t size);
int unlink(const char *path);
int rmdir(const char *path);
int access(const char *path, int mode);
int faccessat(int directory_fd, const char *path, int mode, int flags);
int fsync(int fd);
int fdatasync(int fd);
int truncate(const char *path, off_t length);
int ftruncate(int fd, off_t length);
pid_t getpid(void);
pid_t getppid(void);
pid_t gettid(void);
uid_t getuid(void);
uid_t geteuid(void);
gid_t getgid(void);
gid_t getegid(void);
unsigned int sleep(unsigned int seconds);
int brk(void *address);
void *sbrk(intptr_t increment);
void _exit(int status) __attribute__((noreturn));

#define SEEK_SET 0
#define SEEK_CUR 1
#define SEEK_END 2

#define F_OK 0
#define X_OK 1
#define W_OK 2
#define R_OK 4

#endif
