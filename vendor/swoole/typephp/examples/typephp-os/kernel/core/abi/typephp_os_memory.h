#ifndef TYPEPHP_OS_MEMORY_H
#define TYPEPHP_OS_MEMORY_H

#include <stdint.h>

typedef struct {
    uint64_t total_bytes;
    uint64_t kernel_reserved_bytes;
    uint64_t zend_total_bytes;
    uint64_t zend_free_bytes;
    uint64_t page_total_bytes;
    uint64_t page_free_bytes;
    uint64_t block_cache_bytes;
} typephp_os_memory_info;

#ifdef __cplusplus
extern "C" {
#endif
int typephp_os_get_memory_info(typephp_os_memory_info *info);
#ifdef __cplusplus
}
#endif

#endif
