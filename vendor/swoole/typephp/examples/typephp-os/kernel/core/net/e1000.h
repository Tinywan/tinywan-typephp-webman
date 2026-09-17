#ifndef TYPEPHP_OS_E1000_H
#define TYPEPHP_OS_E1000_H

#include <stddef.h>
#include <stdint.h>

int typephp_e1000_init(void);
const uint8_t *typephp_e1000_mac(void);
int typephp_e1000_transmit(const void *data, size_t length);
int typephp_e1000_receive(void *data, size_t capacity, size_t *length);

#endif
