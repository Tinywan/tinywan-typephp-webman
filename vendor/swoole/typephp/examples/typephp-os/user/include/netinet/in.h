#ifndef TYPEPHP_OS_USER_NETINET_IN_H
#define TYPEPHP_OS_USER_NETINET_IN_H

#include <stdint.h>
#include <sys/socket.h>

typedef uint16_t in_port_t;
typedef uint32_t in_addr_t;

struct in_addr {
    in_addr_t s_addr;
};

struct sockaddr_in {
    sa_family_t sin_family;
    in_port_t sin_port;
    struct in_addr sin_addr;
    unsigned char sin_zero[8];
};

#define IPPROTO_IP 0
#define IPPROTO_TCP 6

#define INADDR_ANY ((in_addr_t) 0x00000000)
#define INADDR_LOOPBACK ((in_addr_t) 0x0100007f)
#define INADDR_NONE ((in_addr_t) 0xffffffff)

#define TCP_NODELAY 1

static inline uint16_t htons(uint16_t value)
{
    return __builtin_bswap16(value);
}

static inline uint16_t ntohs(uint16_t value)
{
    return __builtin_bswap16(value);
}

static inline uint32_t htonl(uint32_t value)
{
    return __builtin_bswap32(value);
}

static inline uint32_t ntohl(uint32_t value)
{
    return __builtin_bswap32(value);
}

#endif
