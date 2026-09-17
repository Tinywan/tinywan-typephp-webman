#ifndef TYPEPHP_OS_USER_SYS_MMAN_H
#define TYPEPHP_OS_USER_SYS_MMAN_H

#include <stddef.h>

#define PROT_NONE 0
#define PROT_READ 1
#define PROT_WRITE 2
#define PROT_EXEC 4

#define MAP_PRIVATE 2
#define MAP_ANONYMOUS 0x20
#define MAP_FAILED ((void *) -1)

void *mmap(void *address, size_t length, int protection, int flags, int fd, long offset);
int mprotect(void *address, size_t length, int protection);
int munmap(void *address, size_t length);

#endif
