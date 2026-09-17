#ifndef TYPEPHP_OS_USER_ERRNO_H
#define TYPEPHP_OS_USER_ERRNO_H

int *__errno_location(void);
#define errno (*__errno_location())

#define ENOENT 2
#define EIO 5
#define ENOEXEC 8
#define EBADF 9
#define ENOMEM 12
#define EACCES 13
#define EFAULT 14
#define EEXIST 17
#define ENOTDIR 20
#define EISDIR 21
#define EINVAL 22
#define EMFILE 24
#define ENOTTY 25
#define EROFS 30
#define ERANGE 34
#define ENOSPC 28
#define EAGAIN 11
#define EWOULDBLOCK EAGAIN
#define EPIPE 32
#define ENAMETOOLONG 36
#define ENOSYS 38
#define ENOTEMPTY 39
#define EINPROGRESS 115
#define EALREADY 114
#define ENOTSOCK 88
#define EDESTADDRREQ 89
#define EMSGSIZE 90
#define EPROTOTYPE 91
#define ENOPROTOOPT 92
#define EPROTONOSUPPORT 93
#define ESOCKTNOSUPPORT 94
#define EOPNOTSUPP 95
#define EAFNOSUPPORT 97
#define EADDRINUSE 98
#define EADDRNOTAVAIL 99
#define ENETDOWN 100
#define ENETUNREACH 101
#define ECONNABORTED 103
#define ECONNRESET 104
#define ENOBUFS 105
#define EISCONN 106
#define ENOTCONN 107
#define ETIMEDOUT 110
#define ECONNREFUSED 111
#define EHOSTUNREACH 113

#endif
