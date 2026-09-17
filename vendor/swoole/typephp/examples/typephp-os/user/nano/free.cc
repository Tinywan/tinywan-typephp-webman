#include <phpx.h>
#include <typephp_os_memory.h>

php::Int php_os_memory_value(php::Int field)
{
    typephp_os_memory_info info;
    if (typephp_os_get_memory_info(&info) != 0) {
        return -1;
    }
    switch (field) {
    case 0: return static_cast<php::Int>(info.total_bytes);
    case 1: return static_cast<php::Int>(info.kernel_reserved_bytes);
    case 2: return static_cast<php::Int>(info.zend_total_bytes);
    case 3: return static_cast<php::Int>(info.zend_free_bytes);
    case 4: return static_cast<php::Int>(info.page_total_bytes);
    case 5: return static_cast<php::Int>(info.page_free_bytes);
    case 6: return static_cast<php::Int>(info.block_cache_bytes);
    default: return -1;
    }
}
