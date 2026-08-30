#include <php_typephp_ocean_func_decl.h>

#ifdef _WIN32
#define TYPEPHP_OCEAN_API extern "C" __declspec(dllexport)
#else
#define TYPEPHP_OCEAN_API extern "C" __attribute__((visibility("default")))
#endif

#include <typephp_runtime.h>

TYPEPHP_RUNTIME_INIT_FUNCTION(typephp_ocean);
TYPEPHP_RUNTIME_SHUTDOWN_FUNCTION(typephp_ocean);

static bool g_typephp_ocean_initialized = false;

static int typephp_ocean_ensure_runtime()
{
    if (g_typephp_ocean_initialized) {
        return 1;
    }

    char app_name[] = "typephp_ocean";
    char *argv[] = {app_name, nullptr};
    if (TYPEPHP_RUNTIME_INIT(typephp_ocean)(1, argv) != 0) {
        return 0;
    }

    g_typephp_ocean_initialized = true;
    return 1;
}

TYPEPHP_OCEAN_API int typephp_ocean_init()
{
    return typephp_ocean_ensure_runtime();
}

TYPEPHP_OCEAN_API void typephp_ocean_shutdown()
{
    if (!g_typephp_ocean_initialized) {
        return;
    }
    TYPEPHP_RUNTIME_SHUTDOWN(typephp_ocean)();
    g_typephp_ocean_initialized = false;
}

TYPEPHP_OCEAN_API int typephp_ocean_island_count()
{
    return typephp_ocean_ensure_runtime() ? static_cast<int>(php_ocean_island_count()) : 0;
}

TYPEPHP_OCEAN_API double typephp_ocean_island_x(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_island_x(index)) : 0.0;
}

TYPEPHP_OCEAN_API double typephp_ocean_island_z(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_island_z(index)) : 0.0;
}

TYPEPHP_OCEAN_API double typephp_ocean_island_radius(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_island_radius(index)) : 0.0;
}

TYPEPHP_OCEAN_API double typephp_ocean_island_height(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_island_height(index)) : 0.0;
}

TYPEPHP_OCEAN_API double typephp_ocean_island_seed(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_island_seed(index)) : 0.0;
}

TYPEPHP_OCEAN_API int typephp_ocean_marker_count()
{
    return typephp_ocean_ensure_runtime() ? static_cast<int>(php_ocean_marker_count()) : 0;
}

TYPEPHP_OCEAN_API double typephp_ocean_marker_x(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_marker_x(index)) : 0.0;
}

TYPEPHP_OCEAN_API double typephp_ocean_marker_z(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_marker_z(index)) : 0.0;
}

TYPEPHP_OCEAN_API int typephp_ocean_marker_type(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<int>(php_ocean_marker_type(index)) : 0;
}

TYPEPHP_OCEAN_API double typephp_ocean_marker_size(int index)
{
    return typephp_ocean_ensure_runtime() ? static_cast<double>(php_ocean_marker_size(index)) : 0.0;
}

TYPEPHP_OCEAN_API int typephp_ocean_choose_next_weather(int current, double roll)
{
    return typephp_ocean_ensure_runtime() ? static_cast<int>(php_ocean_choose_next_weather(current, roll)) : current;
}
