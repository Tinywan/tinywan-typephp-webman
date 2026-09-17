#include <arpa/inet.h>
#include <errno.h>
#include <netdb.h>
#include <netinet/in.h>
#include <poll.h>
#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/syscall.h>
#include <sys/time.h>

long syscall(long number, ...);

int socket(int domain, int type, int protocol)
{
    return (int) syscall(SYS_socket, domain, type, protocol);
}

int connect(int fd, const struct sockaddr *address, socklen_t length)
{
    return (int) syscall(SYS_connect, fd, address, length);
}

int bind(int fd, const struct sockaddr *address, socklen_t length)
{
    return (int) syscall(SYS_bind, fd, address, length);
}

ssize_t sendto(int fd, const void *buffer, size_t length, int flags,
    const struct sockaddr *address, socklen_t address_length)
{
    return (ssize_t) syscall(SYS_sendto, fd, buffer, length, flags,
        address, address_length);
}

ssize_t recvfrom(int fd, void *buffer, size_t length, int flags,
    struct sockaddr *address, socklen_t *address_length)
{
    return (ssize_t) syscall(SYS_recvfrom, fd, buffer, length, flags,
        address, address_length);
}

ssize_t send(int fd, const void *buffer, size_t length, int flags)
{
    return sendto(fd, buffer, length, flags, 0, 0);
}

ssize_t recv(int fd, void *buffer, size_t length, int flags)
{
    return recvfrom(fd, buffer, length, flags, 0, 0);
}

int shutdown(int fd, int how)
{
    return (int) syscall(SYS_shutdown, fd, how);
}

int getsockname(int fd, struct sockaddr *address, socklen_t *length)
{
    return (int) syscall(SYS_getsockname, fd, address, length);
}

int getpeername(int fd, struct sockaddr *address, socklen_t *length)
{
    return (int) syscall(SYS_getpeername, fd, address, length);
}

int setsockopt(int fd, int level, int option, const void *value, socklen_t length)
{
    return (int) syscall(SYS_setsockopt, fd, level, option, value, length);
}

int getsockopt(int fd, int level, int option, void *value, socklen_t *length)
{
    return (int) syscall(SYS_getsockopt, fd, level, option, value, length);
}

int poll(struct pollfd *fds, nfds_t count, int timeout)
{
    return (int) syscall(SYS_poll, fds, count, timeout);
}

int typephp_os_select(int count, void *read_set_pointer, void *write_set_pointer,
    void *except_set_pointer, struct timeval *timeout) __asm__("select");

int typephp_os_select(int count, void *read_set_pointer, void *write_set_pointer,
    void *except_set_pointer, struct timeval *timeout)
{
    enum { BITS_PER_WORD = (int) (sizeof(unsigned long) * 8), MAX_FDS = 1024 };
    unsigned long *read_set = (unsigned long *) read_set_pointer;
    unsigned long *write_set = (unsigned long *) write_set_pointer;
    unsigned long *except_set = (unsigned long *) except_set_pointer;
    struct pollfd fds[MAX_FDS];
    int fd_map[MAX_FDS];
    int poll_count = 0;
    int milliseconds = -1;
    int ready;
    int fd;
    if (count < 0 || count > MAX_FDS) {
        errno = EINVAL;
        return -1;
    }
    if (timeout != 0) {
        int64_t value = (int64_t) timeout->tv_sec * 1000
            + (timeout->tv_usec + 999) / 1000;
        milliseconds = value > INT32_MAX ? INT32_MAX : (int) value;
    }
    for (fd = 0; fd < count; ++fd) {
        const unsigned long mask = 1ul << (fd % BITS_PER_WORD);
        short events = 0;
        if (read_set != 0 && (read_set[fd / BITS_PER_WORD] & mask) != 0) {
            events |= POLLIN;
        }
        if (write_set != 0 && (write_set[fd / BITS_PER_WORD] & mask) != 0) {
            events |= POLLOUT;
        }
        if (except_set != 0 && (except_set[fd / BITS_PER_WORD] & mask) != 0) {
            events |= POLLERR;
        }
        if (events != 0) {
            fds[poll_count].fd = fd;
            fds[poll_count].events = events;
            fds[poll_count].revents = 0;
            fd_map[poll_count++] = fd;
        }
    }
    if (read_set != 0) {
        memset(read_set, 0, ((size_t) count + BITS_PER_WORD - 1) / BITS_PER_WORD
            * sizeof(unsigned long));
    }
    if (write_set != 0) {
        memset(write_set, 0, ((size_t) count + BITS_PER_WORD - 1) / BITS_PER_WORD
            * sizeof(unsigned long));
    }
    if (except_set != 0) {
        memset(except_set, 0, ((size_t) count + BITS_PER_WORD - 1) / BITS_PER_WORD
            * sizeof(unsigned long));
    }
    ready = poll(fds, (nfds_t) poll_count, milliseconds);
    if (ready <= 0) {
        return ready;
    }
    ready = 0;
    for (fd = 0; fd < poll_count; ++fd) {
        const int selected = fd_map[fd];
        const unsigned long mask = 1ul << (selected % BITS_PER_WORD);
        int marked = 0;
        if (read_set != 0 && (fds[fd].revents & (POLLIN | POLLHUP)) != 0) {
            read_set[selected / BITS_PER_WORD] |= mask;
            marked = 1;
        }
        if (write_set != 0 && (fds[fd].revents & POLLOUT) != 0) {
            write_set[selected / BITS_PER_WORD] |= mask;
            marked = 1;
        }
        if (except_set != 0 && (fds[fd].revents & (POLLERR | POLLNVAL)) != 0) {
            except_set[selected / BITS_PER_WORD] |= mask;
            marked = 1;
        }
        ready += marked;
    }
    return ready;
}

static int parse_ipv4(const char *text, uint32_t *address)
{
    uint32_t octets[4] = {0, 0, 0, 0};
    unsigned int part = 0;
    int digits = 0;
    if (text == 0 || *text == '\0') {
        return 0;
    }
    while (*text != '\0') {
        if (*text >= '0' && *text <= '9') {
            octets[part] = octets[part] * 10u + (unsigned int) (*text - '0');
            if (++digits > 3 || octets[part] > 255u) {
                return 0;
            }
        } else if (*text == '.' && digits != 0 && part < 3) {
            ++part;
            digits = 0;
        } else {
            return 0;
        }
        ++text;
    }
    if (part != 3 || digits == 0) {
        return 0;
    }
    *address = octets[0] | (octets[1] << 8u)
        | (octets[2] << 16u) | (octets[3] << 24u);
    return 1;
}

int inet_pton(int family, const char *text, void *address)
{
    uint32_t value;
    if (family != AF_INET) {
        errno = EAFNOSUPPORT;
        return -1;
    }
    if (address == 0 || !parse_ipv4(text, &value)) {
        return 0;
    }
    *(uint32_t *) address = value;
    return 1;
}

int inet_aton(const char *text, struct in_addr *address)
{
    return address != 0 && inet_pton(AF_INET, text, address) == 1;
}

in_addr_t inet_addr(const char *text)
{
    in_addr_t address;
    return parse_ipv4(text, &address) ? address : INADDR_NONE;
}

static char *append_octet(char *output, unsigned int value)
{
    if (value >= 100) {
        *output++ = (char) ('0' + value / 100u);
        value %= 100u;
        *output++ = (char) ('0' + value / 10u);
    } else if (value >= 10) {
        *output++ = (char) ('0' + value / 10u);
    }
    *output++ = (char) ('0' + value % 10u);
    return output;
}

const char *inet_ntop(int family, const void *address, char *text,
    socklen_t length)
{
    char buffer[INET_ADDRSTRLEN];
    char *output = buffer;
    const unsigned char *bytes = (const unsigned char *) address;
    unsigned int index;
    if (family != AF_INET) {
        errno = EAFNOSUPPORT;
        return 0;
    }
    if (address == 0 || text == 0) {
        errno = EFAULT;
        return 0;
    }
    for (index = 0; index < 4; ++index) {
        output = append_octet(output, bytes[index]);
        if (index != 3) {
            *output++ = '.';
        }
    }
    *output = '\0';
    if ((size_t) (output - buffer + 1) > length) {
        errno = ENOSPC;
        return 0;
    }
    memcpy(text, buffer, (size_t) (output - buffer + 1));
    return text;
}

static int service_port(const char *service, uint16_t *port)
{
    unsigned int value = 0;
    if (service == 0) {
        *port = 0;
        return 1;
    }
    if (strcmp(service, "http") == 0) {
        *port = 80;
        return 1;
    }
    if (strcmp(service, "https") == 0) {
        *port = 443;
        return 1;
    }
    if (*service == '\0') {
        return 0;
    }
    while (*service != '\0') {
        if (*service < '0' || *service > '9') {
            return 0;
        }
        value = value * 10u + (unsigned int) (*service++ - '0');
        if (value > 65535u) {
            return 0;
        }
    }
    *port = (uint16_t) value;
    return 1;
}

int getaddrinfo(const char *node, const char *service,
    const struct addrinfo *hints, struct addrinfo **result)
{
    struct addrinfo *entry;
    struct sockaddr_in *address;
    uint16_t port;
    uint32_t ipv4;
    int flags = hints != 0 ? hints->ai_flags : 0;
    int family = hints != 0 ? hints->ai_family : AF_UNSPEC;
    int socktype = hints != 0 ? hints->ai_socktype : 0;
    int protocol = hints != 0 ? hints->ai_protocol : 0;
    const int supported_flags = AI_PASSIVE | AI_CANONNAME | AI_NUMERICHOST
        | AI_ADDRCONFIG | AI_NUMERICSERV;
    if (result == 0) {
        return EAI_FAIL;
    }
    *result = 0;
    if ((flags & ~supported_flags) != 0) {
        return EAI_BADFLAGS;
    }
    if (family != AF_UNSPEC && family != AF_INET) {
        return EAI_FAMILY;
    }
    if (socktype != 0 && socktype != SOCK_STREAM) {
        return EAI_SOCKTYPE;
    }
    if (protocol != 0 && protocol != IPPROTO_TCP) {
        return EAI_SERVICE;
    }
    if (!service_port(service, &port)) {
        return EAI_SERVICE;
    }
    if (node == 0) {
        ipv4 = (flags & AI_PASSIVE) != 0 ? INADDR_ANY : INADDR_LOOPBACK;
    } else if (!parse_ipv4(node, &ipv4)) {
        long status;
        if ((flags & AI_NUMERICHOST) != 0) {
            return EAI_NONAME;
        }
        status = syscall(SYS_typephp_dns_resolve_ipv4, node, &ipv4);
        if (status != 0) {
            return errno == ETIMEDOUT || errno == EAGAIN ? EAI_AGAIN : EAI_NONAME;
        }
    }
    entry = (struct addrinfo *) calloc(1,
        sizeof(*entry) + sizeof(*address));
    if (entry == 0) {
        return EAI_MEMORY;
    }
    address = (struct sockaddr_in *) (entry + 1);
    address->sin_family = AF_INET;
    address->sin_port = htons(port);
    address->sin_addr.s_addr = ipv4;
    entry->ai_flags = flags;
    entry->ai_family = AF_INET;
    entry->ai_socktype = socktype != 0 ? socktype : SOCK_STREAM;
    entry->ai_protocol = protocol != 0 ? protocol : IPPROTO_TCP;
    entry->ai_addrlen = sizeof(*address);
    entry->ai_addr = (struct sockaddr *) address;
    entry->ai_next = 0;
    *result = entry;
    return 0;
}

void freeaddrinfo(struct addrinfo *result)
{
    free(result);
}

const char *gai_strerror(int error)
{
    switch (error) {
    case 0: return "Success";
    case EAI_BADFLAGS: return "Invalid flags";
    case EAI_NONAME: return "Name or service not known";
    case EAI_AGAIN: return "Temporary failure in name resolution";
    case EAI_FAIL: return "Non-recoverable name resolution failure";
    case EAI_FAMILY: return "Address family not supported";
    case EAI_SOCKTYPE: return "Socket type not supported";
    case EAI_SERVICE: return "Service not supported";
    case EAI_MEMORY: return "Memory allocation failure";
    case EAI_SYSTEM: return "System error";
    default: return "Unknown address resolution error";
    }
}
