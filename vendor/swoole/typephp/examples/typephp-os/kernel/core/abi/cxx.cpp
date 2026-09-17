/*
   +----------------------------------------------------------------------+
   | TypePHP OS                                                          |
   +----------------------------------------------------------------------+
   | Freestanding C++ allocation ABI backed by Zend MM.                  |
   | SPDX-License-Identifier: BSD-3-Clause                               |
   +----------------------------------------------------------------------+
*/

extern "C" {
#include <zend_alloc.h>
#include "typephp_os_abi.h"
}

#include <cstddef>
#include <cstdint>
#include <new>

extern "C" {
void *__dso_handle = &__dso_handle;

int __cxa_atexit(void (*destructor)(void *), void *argument, void *dso)
{
    (void) destructor;
    (void) argument;
    (void) dso;
    return 0;
}
}

namespace {

void *zend_allocate(std::size_t size)
{
    void *memory = emalloc(size == 0 ? 1 : size);
    if (memory == nullptr) {
        typephp_os_panic("C++ allocation failed");
    }
    return memory;
}

void *zend_allocate_aligned(std::size_t size, std::size_t alignment)
{
    if (alignment <= alignof(std::max_align_t)) {
        return zend_allocate(size);
    }
    const std::size_t extra = alignment - 1 + sizeof(void *);
    if (size > static_cast<std::size_t>(-1) - extra) {
        typephp_os_panic("C++ aligned allocation overflow");
    }
    void *base = zend_allocate(size + extra);
    const auto start = reinterpret_cast<std::uintptr_t>(base) + sizeof(void *);
    const auto aligned = (start + alignment - 1) & ~(static_cast<std::uintptr_t>(alignment) - 1);
    void **result = reinterpret_cast<void **>(aligned);
    result[-1] = base;
    return result;
}

void zend_free_aligned(void *memory, std::size_t alignment) noexcept
{
    if (memory == nullptr) {
        return;
    }
    if (alignment <= alignof(std::max_align_t)) {
        efree(memory);
        return;
    }
    efree(reinterpret_cast<void **>(memory)[-1]);
}

} // namespace

void *operator new(std::size_t size)
{
    return zend_allocate(size);
}

void *operator new[](std::size_t size)
{
    return zend_allocate(size);
}

void *operator new(std::size_t size, const std::nothrow_t &) noexcept
{
    return zend_allocate(size);
}

void *operator new[](std::size_t size, const std::nothrow_t &) noexcept
{
    return zend_allocate(size);
}

void operator delete(void *memory) noexcept
{
    if (memory != nullptr) {
        efree(memory);
    }
}

void operator delete[](void *memory) noexcept
{
    if (memory != nullptr) {
        efree(memory);
    }
}

void operator delete(void *memory, std::size_t) noexcept
{
    ::operator delete(memory);
}

void operator delete[](void *memory, std::size_t) noexcept
{
    ::operator delete[](memory);
}

void operator delete(void *memory, const std::nothrow_t &) noexcept
{
    ::operator delete(memory);
}

void operator delete[](void *memory, const std::nothrow_t &) noexcept
{
    ::operator delete[](memory);
}

void *operator new(std::size_t size, std::align_val_t alignment)
{
    return zend_allocate_aligned(size, static_cast<std::size_t>(alignment));
}

void *operator new[](std::size_t size, std::align_val_t alignment)
{
    return zend_allocate_aligned(size, static_cast<std::size_t>(alignment));
}

void *operator new(
    std::size_t size, std::align_val_t alignment, const std::nothrow_t &) noexcept
{
    return zend_allocate_aligned(size, static_cast<std::size_t>(alignment));
}

void *operator new[](
    std::size_t size, std::align_val_t alignment, const std::nothrow_t &) noexcept
{
    return zend_allocate_aligned(size, static_cast<std::size_t>(alignment));
}

void operator delete(void *memory, std::align_val_t alignment) noexcept
{
    zend_free_aligned(memory, static_cast<std::size_t>(alignment));
}

void operator delete[](void *memory, std::align_val_t alignment) noexcept
{
    zend_free_aligned(memory, static_cast<std::size_t>(alignment));
}

void operator delete(void *memory, std::size_t, std::align_val_t alignment) noexcept
{
    zend_free_aligned(memory, static_cast<std::size_t>(alignment));
}

void operator delete[](void *memory, std::size_t, std::align_val_t alignment) noexcept
{
    zend_free_aligned(memory, static_cast<std::size_t>(alignment));
}

void operator delete(
    void *memory, std::align_val_t alignment, const std::nothrow_t &) noexcept
{
    zend_free_aligned(memory, static_cast<std::size_t>(alignment));
}

void operator delete[](
    void *memory, std::align_val_t alignment, const std::nothrow_t &) noexcept
{
    zend_free_aligned(memory, static_cast<std::size_t>(alignment));
}

/* libstdc++'s header-only containers call these ABI hooks on impossible or
 * allocation-failure paths.  The kernel deliberately does not link the
 * hosted libstdc++ runtime, so terminate through its panic boundary. */
namespace std {

const nothrow_t nothrow{};

[[noreturn]] void terminate() noexcept
{
    typephp_os_panic("C++ termination");
}

[[noreturn]] void __throw_length_error(const char *)
{
    typephp_os_panic("C++ container length error");
}

[[noreturn]] void __throw_bad_alloc()
{
    typephp_os_panic("C++ allocation failed");
}

[[noreturn]] void __throw_bad_array_new_length()
{
    typephp_os_panic("C++ array allocation failed");
}

} // namespace std
