#include <php_typephp_world_func_decl.h>

#ifdef _WIN32
#define TYPEPHP_WORLD_API extern "C" __declspec(dllexport)
#else
#define TYPEPHP_WORLD_API extern "C" __attribute__((visibility("default")))
#endif

enum DemoBlockType {
    DEMO_BLOCK_GRASS_C = 1,
    DEMO_BLOCK_SAND_C = 2,
    DEMO_BLOCK_STONE_C = 3,
    DEMO_BLOCK_WOOD_C = 5,
    DEMO_BLOCK_DIRT_C = 7,
    DEMO_BLOCK_LEAF_C = 15,
    DEMO_BLOCK_WATER_C = 64,
};

static constexpr int DEMO_WATER_LEVEL_C = 4;

#include <typephp_runtime.h>

TYPEPHP_RUNTIME_INIT_FUNCTION(typephp_world);
TYPEPHP_RUNTIME_SHUTDOWN_FUNCTION(typephp_world);

static bool g_typephp_world_initialized = false;

static int typephp_world_ensure_runtime()
{
    if (g_typephp_world_initialized) {
        return 1;
    }

    char app_name[] = "typephp_world";
    char *argv[] = {app_name, nullptr};
    if (TYPEPHP_RUNTIME_INIT(typephp_world)(1, argv) != 0) {
        return 0;
    }

    g_typephp_world_initialized = true;
    return 1;
}

TYPEPHP_WORLD_API int typephp_world_init()
{
    return typephp_world_ensure_runtime();
}

TYPEPHP_WORLD_API void typephp_world_shutdown()
{
    if (!g_typephp_world_initialized) {
        return;
    }

    TYPEPHP_RUNTIME_SHUTDOWN(typephp_world)();
    g_typephp_world_initialized = false;
}

TYPEPHP_WORLD_API int typephp_world_height_at(int x, int z)
{
    if (!typephp_world_ensure_runtime()) {
        return 0;
    }
    return static_cast<int>(php_demo_world_height_at(x, z));
}

TYPEPHP_WORLD_API int typephp_world_is_river(int x, int z)
{
    if (!typephp_world_ensure_runtime()) {
        return 0;
    }
    return php_demo_world_is_river(x, z) ? 1 : 0;
}

TYPEPHP_WORLD_API int typephp_world_water_level()
{
    return DEMO_WATER_LEVEL_C;
}

TYPEPHP_WORLD_API int typephp_world_block_type_at(int x, int y, int z)
{
    if (!typephp_world_ensure_runtime()) {
        return -1;
    }
    return static_cast<int>(php_demo_world_block_type_at(x, y, z));
}
