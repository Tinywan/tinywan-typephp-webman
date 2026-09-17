/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | Freestanding host contract owned by the TypePHP OS experiment.      |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

#ifndef TYPEPHP_OS_ABI_H
#define TYPEPHP_OS_ABI_H

#include <stddef.h>

#ifdef __cplusplus
extern "C" {
#endif

/* Install the contiguous physical-memory arena used by the libc/POSIX shim.
 * The caller owns page-table setup and must keep the whole region mapped. */
void typephp_os_memory_init(void *address, size_t size);
size_t typephp_os_memory_total(void);
size_t typephp_os_memory_available(void);

/* C++17 global new/delete are supplied by TypePHP OS and allocate
 * exclusively through Zend MM (emalloc/efree). */

/* A platform may override these weak hooks for diagnostics and shutdown. */
void typephp_os_write(const char *data, size_t size);
#ifdef __cplusplus
[[noreturn]]
#else
_Noreturn
#endif
void typephp_os_panic(const char *message);

/* Generic PHPX no-exception policy hook. */
void phpx_no_exception_abort(const char *message);

/* Minimal process/console/filesystem contracts used by the Ring-3 syscall
 * boundary. The filesystem implementations continue to live in TypePHP. */
void typephp_os_process_start(void);
long typephp_os_fs_path_type(const char *path);
long typephp_os_fs_getdents64(int fd, void *buffer, size_t capacity);
long typephp_os_console_read(void *buffer, size_t size);
void typephp_os_console_enable_interrupts(void);
void typephp_os_console_interrupt(void);

#ifdef __cplusplus
}
#endif

#endif
