#include <typephp_os_syscall.h>
#include <typephp_os_memory.h>

long syscall(long number, ...);

int typephp_os_get_memory_info(typephp_os_memory_info *info)
{
    return (int) syscall(TYPEPHP_SYS_MEMORY_INFO, info);
}
