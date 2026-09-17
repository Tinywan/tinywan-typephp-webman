#ifndef TYPEPHP_OS_LWIP_ARCH_CC_H
#define TYPEPHP_OS_LWIP_ARCH_CC_H

#include <stdint.h>
#include <limits.h>

typedef long ssize_t;
#ifndef SSIZE_MAX
#define SSIZE_MAX LONG_MAX
#endif

#define BYTE_ORDER LITTLE_ENDIAN
#define LWIP_NO_UNISTD_H 1
#define LWIP_NO_CTYPE_H 1
#define LWIP_PLATFORM_DIAG(message) do { } while (0)

void typephp_network_assert(const char *message);
uint32_t typephp_network_random_u32(void);

#define LWIP_PLATFORM_ASSERT(message) typephp_network_assert(message)
#define LWIP_RAND() typephp_network_random_u32()

#endif
