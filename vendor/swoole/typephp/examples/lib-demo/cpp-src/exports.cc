#define TYPEPHP_LIB_DEMO_BUILD
#include "../include/typephp_lib_demo.h"
#include <phpx.h>

#include <typephp_runtime.h>

TYPEPHP_RUNTIME_INIT_FUNCTION(demo);
extern php::Int php_demo_add(php::Int a, php::Int b);

extern "C" TYPEPHP_LIB_DEMO_API int typephp_lib_demo_add(int a, int b)
{
    char app_name[] = "typephp_lib_demo";
    char *argv[] = {app_name, nullptr};
    if (TYPEPHP_RUNTIME_INIT(demo)(1, argv) != 0) {
        return 0;
    }
    return static_cast<int>(php_demo_add(a, b));
}
