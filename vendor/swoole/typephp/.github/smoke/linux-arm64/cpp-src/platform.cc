#include <phpx.h>

#include <cstring>
#include <sys/utsname.h>
#include <unistd.h>

using namespace php;

Int php_linux_current_process_id()
{
    return static_cast<Int>(getpid());
}

Int php_linux_online_processor_count()
{
    return static_cast<Int>(sysconf(_SC_NPROCESSORS_ONLN));
}

Bool php_linux_uname_machine_is_arm64()
{
    utsname info{};
    if (uname(&info) != 0) {
        return false;
    }
    return std::strcmp(info.machine, "aarch64") == 0 || std::strcmp(info.machine, "arm64") == 0;
}

Bool php_linux_native_is_arm64()
{
#if defined(__aarch64__) || defined(__arm64__)
    return true;
#else
    return false;
#endif
}

Bool php_linux_native_php_is_zts()
{
#ifdef ZTS
    return true;
#else
    return false;
#endif
}
