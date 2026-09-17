#ifndef TYPEPHP_OS_NETWORK_H
#define TYPEPHP_OS_NETWORK_H

#include <stdint.h>

int typephp_network_init(void);
int typephp_network_ready(void);
void typephp_network_poll(void);
uint64_t typephp_network_milliseconds(void);
long typephp_network_resolve_ipv4(const char *hostname, uint32_t *address);

#endif
