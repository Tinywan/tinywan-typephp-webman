/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | Linux-compatible userspace sockets backed by kernel-owned lwIP TCP. |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

#include "socket.h"
#include "net/network.h"

#include <errno.h>
#include <fcntl.h>
#include <stdint.h>
#include <string.h>

#include "lwip/ip_addr.h"
#include "lwip/pbuf.h"
#include "lwip/tcp.h"

enum {
    SOCKET_FD_BEGIN = 128,
    SOCKET_COUNT = 256,
    LINUX_AF_INET = 2,
    LINUX_SOCK_STREAM = 1,
    LINUX_SOCK_TYPE_MASK = 15,
    LINUX_SOCK_NONBLOCK = 04000,
    LINUX_SOCK_CLOEXEC = 02000000,
    LINUX_IPPROTO_TCP = 6,
    LINUX_SOL_SOCKET = 1,
    LINUX_SO_ERROR = 4,
    LINUX_SO_KEEPALIVE = 9,
    LINUX_SO_RCVBUF = 8,
    LINUX_SO_SNDBUF = 7,
    LINUX_TCP_NODELAY = 1,
    LINUX_F_GETFD = 1,
    LINUX_F_SETFD = 2,
    LINUX_F_GETFL = 3,
    LINUX_F_SETFL = 4,
    LINUX_FD_CLOEXEC = 1,
    LINUX_O_RDWR = 2,
    LINUX_O_NONBLOCK = 04000,
    LINUX_MSG_PEEK = 2,
    LINUX_MSG_DONTWAIT = 0x40,
    LINUX_MSG_NOSIGNAL = 0x4000,
    LINUX_MSG_MORE = 0x8000,
    LINUX_POLLIN = 0x001,
    LINUX_POLLOUT = 0x004,
    LINUX_POLLERR = 0x008,
    LINUX_POLLHUP = 0x010,
    LINUX_POLLNVAL = 0x020,
    SOCKET_NEW = 0,
    SOCKET_CONNECTING = 1,
    SOCKET_CONNECTED = 2,
    SOCKET_PEER_CLOSED = 3,
    SOCKET_FAILED = 4,
};

typedef struct __attribute__((packed)) {
    uint16_t family;
    uint16_t port;
    uint32_t address;
    uint8_t zero[8];
} linux_sockaddr_in;

typedef struct {
    int allocated;
    int domain;
    int type;
    int protocol;
    int status_flags;
    int descriptor_flags;
    int pending_error;
    int state;
    unsigned long owner;
    struct tcp_pcb *pcb;
    struct pbuf *received;
} kernel_socket;

static kernel_socket sockets[SOCKET_COUNT];

static kernel_socket *socket_for_fd(int fd)
{
    if (fd < SOCKET_FD_BEGIN || fd >= SOCKET_FD_BEGIN + SOCKET_COUNT) {
        return 0;
    }
    kernel_socket *socket = &sockets[fd - SOCKET_FD_BEGIN];
    return socket->allocated ? socket : 0;
}

static int network_errno(err_t error)
{
    switch (error) {
    case ERR_OK: return 0;
    case ERR_MEM: return ENOMEM;
    case ERR_BUF: return ENOBUFS;
    case ERR_TIMEOUT: return ETIMEDOUT;
    case ERR_RTE: return ENETUNREACH;
    case ERR_INPROGRESS: return EINPROGRESS;
    case ERR_VAL: return EINVAL;
    case ERR_WOULDBLOCK: return EAGAIN;
    case ERR_USE: return EADDRINUSE;
    case ERR_ALREADY: return EALREADY;
    case ERR_ISCONN: return EISCONN;
    case ERR_ABRT: return ECONNABORTED;
    case ERR_RST: return ECONNRESET;
    case ERR_CLSD: return ENOTCONN;
    case ERR_CONN: return ENOTCONN;
    case ERR_ARG: return EINVAL;
    case ERR_IF:
    default:
        return ENETDOWN;
    }
}

static void wait_for_network_event(void)
{
    typephp_network_poll();
    __asm__ volatile("sti; hlt; cli" : : : "memory");
    typephp_network_poll();
}

static err_t connected_callback(void *argument, struct tcp_pcb *pcb, err_t error)
{
    kernel_socket *socket = (kernel_socket *) argument;
    (void) pcb;
    if (error == ERR_OK) {
        socket->state = SOCKET_CONNECTED;
        socket->pending_error = 0;
    } else {
        socket->state = SOCKET_FAILED;
        socket->pending_error = network_errno(error);
    }
    return ERR_OK;
}

static err_t received_callback(void *argument, struct tcp_pcb *pcb,
    struct pbuf *packet, err_t error)
{
    kernel_socket *socket = (kernel_socket *) argument;
    (void) pcb;
    if (error != ERR_OK) {
        if (packet != 0) {
            pbuf_free(packet);
        }
        socket->pending_error = network_errno(error);
        return ERR_OK;
    }
    if (packet == 0) {
        socket->state = SOCKET_PEER_CLOSED;
        return ERR_OK;
    }
    if (socket->received == 0) {
        socket->received = packet;
    } else {
        pbuf_cat(socket->received, packet);
    }
    return ERR_OK;
}

static void error_callback(void *argument, err_t error)
{
    kernel_socket *socket = (kernel_socket *) argument;
    socket->pcb = 0;
    socket->state = SOCKET_FAILED;
    socket->pending_error = network_errno(error);
}

static void install_callbacks(kernel_socket *socket)
{
    tcp_arg(socket->pcb, socket);
    tcp_recv(socket->pcb, received_callback);
    tcp_err(socket->pcb, error_callback);
}

static void release_socket(kernel_socket *socket)
{
    if (socket->pcb != 0) {
        tcp_arg(socket->pcb, 0);
        tcp_recv(socket->pcb, 0);
        tcp_err(socket->pcb, 0);
        if (tcp_close(socket->pcb) != ERR_OK) {
            tcp_abort(socket->pcb);
        }
    }
    if (socket->received != 0) {
        pbuf_free(socket->received);
    }
    memset(socket, 0, sizeof(*socket));
}

int typephp_socket_is_fd(int fd)
{
    return socket_for_fd(fd) != 0;
}

long typephp_socket_open(unsigned long owner, int domain, int type, int protocol)
{
    const int base_type = type & LINUX_SOCK_TYPE_MASK;
    const int flags = type & ~LINUX_SOCK_TYPE_MASK;
    if (domain != LINUX_AF_INET) {
        return -EAFNOSUPPORT;
    }
    if (base_type != LINUX_SOCK_STREAM) {
        return -ESOCKTNOSUPPORT;
    }
    if ((flags & ~(LINUX_SOCK_NONBLOCK | LINUX_SOCK_CLOEXEC)) != 0) {
        return -EINVAL;
    }
    if (protocol != 0 && protocol != LINUX_IPPROTO_TCP) {
        return -EPROTONOSUPPORT;
    }
    if (!typephp_network_ready()) {
        return -ENETDOWN;
    }
    for (int index = 0; index < SOCKET_COUNT; ++index) {
        if (!sockets[index].allocated) {
            struct tcp_pcb *pcb = tcp_new_ip_type(IPADDR_TYPE_V4);
            if (pcb == 0) {
                return -ENOBUFS;
            }
            kernel_socket *socket = &sockets[index];
            memset(socket, 0, sizeof(*socket));
            socket->allocated = 1;
            socket->domain = domain;
            socket->type = base_type;
            socket->protocol = LINUX_IPPROTO_TCP;
            socket->status_flags = LINUX_O_RDWR
                | ((flags & LINUX_SOCK_NONBLOCK) != 0 ? LINUX_O_NONBLOCK : 0);
            socket->descriptor_flags =
                (flags & LINUX_SOCK_CLOEXEC) != 0 ? LINUX_FD_CLOEXEC : 0;
            socket->owner = owner;
            socket->state = SOCKET_NEW;
            socket->pcb = pcb;
            install_callbacks(socket);
            return SOCKET_FD_BEGIN + index;
        }
    }
    return -EMFILE;
}

long typephp_socket_close(int fd)
{
    kernel_socket *socket = socket_for_fd(fd);
    if (socket == 0) {
        return -EBADF;
    }
    release_socket(socket);
    return 0;
}

long typephp_socket_read(int fd, void *buffer, size_t length)
{
    return typephp_socket_recvfrom(fd, buffer, length, 0, 0, 0);
}

long typephp_socket_write(int fd, const void *buffer, size_t length)
{
    return typephp_socket_sendto(fd, buffer, length, 0, 0, 0);
}

long typephp_socket_connect(int fd, const void *address, typephp_socklen_t length)
{
    kernel_socket *socket = socket_for_fd(fd);
    const linux_sockaddr_in *remote = (const linux_sockaddr_in *) address;
    ip_addr_t remote_address;
    err_t error;
    if (socket == 0) {
        return -EBADF;
    }
    if (address == 0 || length < sizeof(*remote)) {
        return -EINVAL;
    }
    if (remote->family != LINUX_AF_INET) {
        return -EAFNOSUPPORT;
    }
    if (socket->state == SOCKET_CONNECTING) {
        return -EALREADY;
    }
    if (socket->state == SOCKET_CONNECTED) {
        return -EISCONN;
    }
    if (socket->state != SOCKET_NEW || socket->pcb == 0) {
        return -ENOTCONN;
    }
    IP_ADDR4(&remote_address,
        (uint8_t) remote->address,
        (uint8_t) (remote->address >> 8u),
        (uint8_t) (remote->address >> 16u),
        (uint8_t) (remote->address >> 24u));
    socket->state = SOCKET_CONNECTING;
    socket->pending_error = 0;
    error = tcp_connect(socket->pcb, &remote_address,
        lwip_ntohs(remote->port), connected_callback);
    if (error != ERR_OK) {
        socket->state = SOCKET_FAILED;
        socket->pending_error = network_errno(error);
        return -socket->pending_error;
    }
    if ((socket->status_flags & LINUX_O_NONBLOCK) != 0) {
        return -EINPROGRESS;
    }
    while (socket->state == SOCKET_CONNECTING) {
        wait_for_network_event();
    }
    return socket->state == SOCKET_CONNECTED ? 0
        : -(socket->pending_error != 0 ? socket->pending_error : ECONNREFUSED);
}

long typephp_socket_bind(int fd, const void *address, typephp_socklen_t length)
{
    kernel_socket *socket = socket_for_fd(fd);
    const linux_sockaddr_in *local = (const linux_sockaddr_in *) address;
    ip_addr_t local_address;
    err_t error;
    if (socket == 0) {
        return -EBADF;
    }
    if (address == 0 || length < sizeof(*local)) {
        return -EINVAL;
    }
    if (local->family != LINUX_AF_INET) {
        return -EAFNOSUPPORT;
    }
    if (socket->state != SOCKET_NEW || socket->pcb == 0) {
        return -EINVAL;
    }
    IP_ADDR4(&local_address,
        (uint8_t) local->address,
        (uint8_t) (local->address >> 8u),
        (uint8_t) (local->address >> 16u),
        (uint8_t) (local->address >> 24u));
    error = tcp_bind(socket->pcb,
        local->address == 0 ? IP_ANY_TYPE : &local_address,
        lwip_ntohs(local->port));
    return error == ERR_OK ? 0 : -network_errno(error);
}

long typephp_socket_sendto(int fd, const void *buffer, size_t length, int flags,
    const void *address, typephp_socklen_t address_length)
{
    kernel_socket *socket = socket_for_fd(fd);
    const uint8_t *input = (const uint8_t *) buffer;
    size_t written = 0;
    (void) address;
    (void) address_length;
    if (socket == 0) {
        return -EBADF;
    }
    if ((flags & ~(LINUX_MSG_DONTWAIT | LINUX_MSG_NOSIGNAL | LINUX_MSG_MORE)) != 0) {
        return -EOPNOTSUPP;
    }
    if (buffer == 0 && length != 0) {
        return -EFAULT;
    }
    if (socket->state != SOCKET_CONNECTED || socket->pcb == 0) {
        return -ENOTCONN;
    }
    while (written < length) {
        size_t chunk = length - written;
        const size_t available = tcp_sndbuf(socket->pcb);
        if (chunk > UINT16_MAX) {
            chunk = UINT16_MAX;
        }
        if (chunk > available) {
            chunk = available;
        }
        if (chunk == 0) {
            if ((socket->status_flags & LINUX_O_NONBLOCK) != 0
                || (flags & LINUX_MSG_DONTWAIT) != 0) {
                return written != 0 ? (long) written : -EAGAIN;
            }
            wait_for_network_event();
            if (socket->state != SOCKET_CONNECTED || socket->pcb == 0) {
                return written != 0 ? (long) written : -ENOTCONN;
            }
            continue;
        }
        err_t error = tcp_write(socket->pcb, input + written,
            (u16_t) chunk, TCP_WRITE_FLAG_COPY);
        if (error == ERR_MEM) {
            if ((socket->status_flags & LINUX_O_NONBLOCK) != 0
                || (flags & LINUX_MSG_DONTWAIT) != 0) {
                return written != 0 ? (long) written : -EAGAIN;
            }
            wait_for_network_event();
            continue;
        }
        if (error != ERR_OK) {
            return written != 0 ? (long) written : -network_errno(error);
        }
        written += chunk;
        (void) tcp_output(socket->pcb);
        typephp_network_poll();
    }
    return (long) written;
}

long typephp_socket_recvfrom(int fd, void *buffer, size_t length, int flags,
    void *address, typephp_socklen_t *address_length)
{
    kernel_socket *socket = socket_for_fd(fd);
    uint8_t *output = (uint8_t *) buffer;
    size_t copied = 0;
    (void) address;
    (void) address_length;
    if (socket == 0) {
        return -EBADF;
    }
    if ((flags & ~(LINUX_MSG_PEEK | LINUX_MSG_DONTWAIT)) != 0) {
        return -EOPNOTSUPP;
    }
    if (buffer == 0 && length != 0) {
        return -EFAULT;
    }
    if (length == 0) {
        return 0;
    }
    while (socket->received == 0) {
        if (socket->state == SOCKET_PEER_CLOSED) {
            return 0;
        }
        if (socket->state == SOCKET_FAILED) {
            return -(socket->pending_error != 0
                ? socket->pending_error : ECONNRESET);
        }
        if (socket->state != SOCKET_CONNECTED || socket->pcb == 0) {
            return -ENOTCONN;
        }
        if ((socket->status_flags & LINUX_O_NONBLOCK) != 0
            || (flags & LINUX_MSG_DONTWAIT) != 0) {
            return -EAGAIN;
        }
        wait_for_network_event();
    }
    copied = length < socket->received->tot_len
        ? length : socket->received->tot_len;
    if (pbuf_copy_partial(socket->received, output, copied, 0) != copied) {
        return -EIO;
    }
    if ((flags & LINUX_MSG_PEEK) == 0) {
        size_t consumed = copied;
        while (consumed != 0) {
            const u16_t part = consumed > UINT16_MAX
                ? UINT16_MAX : (u16_t) consumed;
            socket->received = pbuf_free_header(socket->received, part);
            tcp_recved(socket->pcb, part);
            consumed -= part;
        }
    }
    return (long) copied;
}

long typephp_socket_shutdown(int fd, int how)
{
    kernel_socket *socket = socket_for_fd(fd);
    err_t error;
    if (socket == 0) {
        return -EBADF;
    }
    if (how < 0 || how > 2) {
        return -EINVAL;
    }
    if (socket->pcb == 0
        || (socket->state != SOCKET_CONNECTED
            && socket->state != SOCKET_PEER_CLOSED)) {
        return -ENOTCONN;
    }
    error = tcp_shutdown(socket->pcb, how != 1, how != 0);
    return error == ERR_OK ? 0 : -network_errno(error);
}

long typephp_socket_getname(int fd, void *address, typephp_socklen_t *length,
    int peer)
{
    kernel_socket *socket = socket_for_fd(fd);
    linux_sockaddr_in result;
    const ip_addr_t *ip;
    uint16_t port;
    if (socket == 0) {
        return -EBADF;
    }
    if (socket->pcb == 0 || (peer && socket->state != SOCKET_CONNECTED)) {
        return -ENOTCONN;
    }
    if (address == 0 || length == 0 || *length < sizeof(result)) {
        return -EINVAL;
    }
    memset(&result, 0, sizeof(result));
    result.family = LINUX_AF_INET;
    ip = peer ? &socket->pcb->remote_ip : &socket->pcb->local_ip;
    port = peer ? socket->pcb->remote_port : socket->pcb->local_port;
    result.address = ip_2_ip4(ip)->addr;
    result.port = lwip_htons(port);
    memcpy(address, &result, sizeof(result));
    *length = sizeof(result);
    return 0;
}

long typephp_socket_setsockopt(int fd, int level, int option,
    const void *value, typephp_socklen_t length)
{
    kernel_socket *socket = socket_for_fd(fd);
    int enabled;
    if (socket == 0) {
        return -EBADF;
    }
    if (value == 0 || length < sizeof(int) || socket->pcb == 0) {
        return -EINVAL;
    }
    enabled = *(const int *) value;
    if (level == LINUX_SOL_SOCKET && option == LINUX_SO_KEEPALIVE) {
        if (enabled) {
            ip_set_option(socket->pcb, SOF_KEEPALIVE);
        } else {
            ip_reset_option(socket->pcb, SOF_KEEPALIVE);
        }
        return 0;
    }
    if (level == LINUX_IPPROTO_TCP && option == LINUX_TCP_NODELAY) {
        if (enabled) {
            tcp_nagle_disable(socket->pcb);
        } else {
            tcp_nagle_enable(socket->pcb);
        }
        return 0;
    }
    return -ENOPROTOOPT;
}

long typephp_socket_getsockopt(int fd, int level, int option,
    void *value, typephp_socklen_t *length)
{
    kernel_socket *socket = socket_for_fd(fd);
    int result;
    if (socket == 0) {
        return -EBADF;
    }
    if (value == 0 || length == 0 || *length < sizeof(int)) {
        return -EINVAL;
    }
    if (level == LINUX_SOL_SOCKET && option == LINUX_SO_ERROR) {
        result = socket->pending_error;
        socket->pending_error = 0;
    } else if (level == LINUX_SOL_SOCKET && option == LINUX_SO_KEEPALIVE) {
        result = socket->pcb != 0 && ip_get_option(socket->pcb, SOF_KEEPALIVE);
    } else if (level == LINUX_SOL_SOCKET && option == LINUX_SO_RCVBUF) {
        result = TCP_WND;
    } else if (level == LINUX_SOL_SOCKET && option == LINUX_SO_SNDBUF) {
        result = TCP_SND_BUF;
    } else if (level == LINUX_IPPROTO_TCP && option == LINUX_TCP_NODELAY) {
        result = socket->pcb != 0 && tcp_nagle_disabled(socket->pcb);
    } else {
        return -ENOPROTOOPT;
    }
    *(int *) value = result;
    *length = sizeof(int);
    return 0;
}

long typephp_socket_fcntl(int fd, int command, long argument)
{
    kernel_socket *socket = socket_for_fd(fd);
    if (socket == 0) {
        return -EBADF;
    }
    switch (command) {
    case LINUX_F_GETFD:
        return socket->descriptor_flags;
    case LINUX_F_SETFD:
        if ((argument & ~LINUX_FD_CLOEXEC) != 0) {
            return -EINVAL;
        }
        socket->descriptor_flags = (int) argument;
        return 0;
    case LINUX_F_GETFL:
        return socket->status_flags;
    case LINUX_F_SETFL:
        if ((argument & ~LINUX_O_NONBLOCK) != 0) {
            return -EINVAL;
        }
        socket->status_flags = (socket->status_flags & ~LINUX_O_NONBLOCK)
            | (int) argument;
        return 0;
    default:
        return -EINVAL;
    }
}

static long poll_once(typephp_pollfd *fds, size_t count)
{
    long ready = 0;
    for (size_t index = 0; index < count; ++index) {
        kernel_socket *socket = socket_for_fd(fds[index].fd);
        fds[index].revents = 0;
        if (socket == 0) {
            fds[index].revents = LINUX_POLLNVAL;
        } else {
            if (socket->pending_error != 0 || socket->state == SOCKET_FAILED) {
                fds[index].revents |= LINUX_POLLERR;
            }
            if ((fds[index].events & LINUX_POLLIN) != 0
                && (socket->received != 0
                    || socket->state == SOCKET_PEER_CLOSED)) {
                fds[index].revents |= LINUX_POLLIN;
            }
            if ((fds[index].events & LINUX_POLLOUT) != 0
                && socket->state == SOCKET_CONNECTED && socket->pcb != 0
                && tcp_sndbuf(socket->pcb) != 0) {
                fds[index].revents |= LINUX_POLLOUT;
            }
            if (socket->state == SOCKET_PEER_CLOSED) {
                fds[index].revents |= LINUX_POLLHUP;
            }
        }
        if (fds[index].revents != 0) {
            ++ready;
        }
    }
    return ready;
}

long typephp_socket_poll(typephp_pollfd *fds, size_t count, int timeout)
{
    const uint64_t start = typephp_network_milliseconds();
    for (;;) {
        typephp_network_poll();
        long ready = poll_once(fds, count);
        if (ready != 0 || timeout == 0) {
            return ready;
        }
        if (timeout > 0
            && typephp_network_milliseconds() - start >= (uint64_t) timeout) {
            return 0;
        }
        wait_for_network_event();
    }
}

void typephp_socket_close_owner(unsigned long owner)
{
    for (int index = 0; index < SOCKET_COUNT; ++index) {
        if (sockets[index].allocated && sockets[index].owner == owner) {
            release_socket(&sockets[index]);
        }
    }
}
