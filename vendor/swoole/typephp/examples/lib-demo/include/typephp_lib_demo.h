#ifndef TYPEPHP_LIB_DEMO_H
#define TYPEPHP_LIB_DEMO_H

#if defined(_WIN32) && defined(TYPEPHP_LIB_DEMO_BUILD)
#define TYPEPHP_LIB_DEMO_API __declspec(dllexport)
#elif defined(_WIN32)
#define TYPEPHP_LIB_DEMO_API __declspec(dllimport)
#else
#define TYPEPHP_LIB_DEMO_API
#endif

#ifdef __cplusplus
extern "C" {
#endif

TYPEPHP_LIB_DEMO_API int typephp_lib_demo_add(int a, int b);

#ifdef __cplusplus
}
#endif

#endif
