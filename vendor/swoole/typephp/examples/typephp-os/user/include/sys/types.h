#ifndef TYPEPHP_OS_USER_SYS_TYPES_H
#define TYPEPHP_OS_USER_SYS_TYPES_H

/* Linux x86-64 ABI-compatible scalar types. Keeping this layout stable makes
 * the userspace boundary suitable for a future glibc port. */
typedef unsigned long dev_t;
typedef unsigned long ino_t;
typedef unsigned long nlink_t;
typedef unsigned int mode_t;
typedef unsigned int uid_t;
typedef unsigned int gid_t;
typedef int pid_t;
typedef long off_t;
typedef long blksize_t;
typedef long blkcnt_t;
typedef long ssize_t;
typedef long time_t;
typedef int clockid_t;

#endif
