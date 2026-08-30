#include <phpx.h>
#include <windows.h>

using namespace php;

Int php_windows_current_process_id()
{
    return static_cast<Int>(GetCurrentProcessId());
}

Bool php_windows_has_module_handle()
{
    return GetModuleHandleW(nullptr) != nullptr;
}

Int php_windows_logical_processor_count()
{
    SYSTEM_INFO info{};
    GetNativeSystemInfo(&info);
    return static_cast<Int>(info.dwNumberOfProcessors);
}

Bool php_windows_php_is_zts()
{
#ifdef ZTS
    return true;
#else
    return false;
#endif
}
