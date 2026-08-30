#include <phpx.h>

#include <mach/mach.h>
#include <sys/sysctl.h>
#include <unistd.h>

using namespace php;

Int php_macos_current_process_id()
{
    return static_cast<Int>(getpid());
}

Int php_macos_logical_processor_count()
{
    int count = 0;
    size_t size = sizeof(count);
    if (sysctlbyname("hw.logicalcpu", &count, &size, nullptr, 0) != 0) {
        return 0;
    }
    return static_cast<Int>(count);
}

Bool php_macos_has_mach_host_port()
{
    return mach_host_self() != MACH_PORT_NULL;
}

Bool php_macos_native_is_arm64()
{
#if defined(__aarch64__) || defined(__arm64__)
    return true;
#else
    return false;
#endif
}

Bool php_macos_native_php_is_zts()
{
#ifdef ZTS
    return true;
#else
    return false;
#endif
}
