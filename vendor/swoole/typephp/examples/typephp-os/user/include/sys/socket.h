#ifndef TYPEPHP_OS_USER_SYS_SOCKET_H
#define TYPEPHP_OS_USER_SYS_SOCKET_H

#include <stddef.h>
#include <stdint.h>
#include <sys/types.h>

typedef uint32_t socklen_t;
typedef uint16_t sa_family_t;

struct sockaddr {
    sa_family_t sa_family;
    char sa_data[14];
};

struct iovec {
    void *iov_base;
    size_t iov_len;
};

#define AF_UNSPEC 0
#define AF_UNIX 1
#define AF_INET 2
#define AF_INET6 10

#define PF_UNSPEC AF_UNSPEC
#define PF_UNIX AF_UNIX
#define PF_INET AF_INET
#define PF_INET6 AF_INET6

#define SOCK_STREAM 1
#define SOCK_DGRAM 2
#define SOCK_RAW 3
#define SOCK_NONBLOCK 04000
#define SOCK_CLOEXEC 02000000

#define SOL_SOCKET 1
#define SO_ERROR 4
#define SO_KEEPALIVE 9
#define SO_RCVBUF 8
#define SO_SNDBUF 7

#define MSG_PEEK 2
#define MSG_DONTWAIT 0x40
#define MSG_NOSIGNAL 0x4000

#define SHUT_RD 0
#define SHUT_WR 1
#define SHUT_RDWR 2

int socket(int domain, int type, int protocol);
int connect(int fd, const struct sockaddr *address, socklen_t length);
int bind(int fd, const struct sockaddr *address, socklen_t length);
ssize_t send(int fd, const void *buffer, size_t length, int flags);
ssize_t recv(int fd, void *buffer, size_t length, int flags);
ssize_t sendto(int fd, const void *buffer, size_t length, int flags,
    const struct sockaddr *address, socklen_t address_length);
ssize_t recvfrom(int fd, void *buffer, size_t length, int flags,
    struct sockaddr *address, socklen_t *address_length);
int shutdown(int fd, int how);
int getsockname(int fd, struct sockaddr *address, socklen_t *length);
int getpeername(int fd, struct sockaddr *address, socklen_t *length);
int setsockopt(int fd, int level, int option, const void *value, socklen_t length);
int getsockopt(int fd, int level, int option, void *value, socklen_t *length);

#endif
