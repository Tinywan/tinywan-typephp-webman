#ifndef TYPEPHP_OS_USER_SYS_RANDOM_H
#define TYPEPHP_OS_USER_SYS_RANDOM_H

#include <stddef.h>
#include <sys/types.h>

#define GRND_NONBLOCK 0x0001
#define GRND_RANDOM 0x0002
#define GRND_INSECURE 0x0004

ssize_t getrandom(void *buffer, size_t length, unsigned int flags);
int getentropy(void *buffer, size_t length);

#endif
