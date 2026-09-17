#ifndef TYPEPHP_OS_KERNEL_SOCKET_H
#define TYPEPHP_OS_KERNEL_SOCKET_H

#include <stddef.h>
#include <stdint.h>

typedef uint32_t typephp_socklen_t;

typedef struct {
    int fd;
    int16_t events;
    int16_t revents;
} typephp_pollfd;

int typephp_socket_is_fd(int fd);
long typephp_socket_open(unsigned long owner, int domain, int type, int protocol);
long typephp_socket_close(int fd);
long typephp_socket_read(int fd, void *buffer, size_t length);
long typephp_socket_write(int fd, const void *buffer, size_t length);
long typephp_socket_connect(int fd, const void *address, typephp_socklen_t length);
long typephp_socket_bind(int fd, const void *address, typephp_socklen_t length);
long typephp_socket_sendto(int fd, const void *buffer, size_t length, int flags,
    const void *address, typephp_socklen_t address_length);
long typephp_socket_recvfrom(int fd, void *buffer, size_t length, int flags,
    void *address, typephp_socklen_t *address_length);
long typephp_socket_shutdown(int fd, int how);
long typephp_socket_getname(int fd, void *address, typephp_socklen_t *length,
    int peer);
long typephp_socket_setsockopt(int fd, int level, int option,
    const void *value, typephp_socklen_t length);
long typephp_socket_getsockopt(int fd, int level, int option,
    void *value, typephp_socklen_t *length);
long typephp_socket_fcntl(int fd, int command, long argument);
long typephp_socket_poll(typephp_pollfd *fds, size_t count, int timeout);
void typephp_socket_close_owner(unsigned long owner);

#endif
