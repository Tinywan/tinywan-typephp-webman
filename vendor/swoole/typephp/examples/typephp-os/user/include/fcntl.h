#ifndef TYPEPHP_OS_USER_FCNTL_H
#define TYPEPHP_OS_USER_FCNTL_H

#define O_RDONLY 0
#define O_WRONLY 1
#define O_RDWR 2
#define O_CREAT 64
#define O_TRUNC 512
#define O_APPEND 1024
#define O_NONBLOCK 2048
#define O_DIRECTORY 65536
#define O_CLOEXEC 524288

#define F_GETFD 1
#define F_SETFD 2
#define F_GETFL 3
#define F_SETFL 4

#define FD_CLOEXEC 1

#define AT_FDCWD (-100)
#define AT_SYMLINK_NOFOLLOW 0x100
#define AT_EACCESS 0x200

int open(const char *path, int flags, ...);
int openat(int directory_fd, const char *path, int flags, ...);
int fcntl(int fd, int command, ...);

#endif
