#include <php_nano_extension.h>
#include <typephp_os_abi.h>

#include <cstdint>
#include <cstddef>
#include <cstring>
#include <sys/syscall.h>
#include <sys/random.h>
#include <time.h>
#include <unistd.h>

extern "C" int typephp_nano_project_main();
extern "C" char **environ;

namespace {

[[noreturn]] void raw_exit(int status)
{
    (void) syscall(SYS_exit, status);
    for (;;) {
        __asm__ volatile("pause");
    }
}

} // namespace

/* Override the lightweight C runtime's default arena. Zend MM acquires
 * aligned 2 MiB chunks while PHPX's native object GC also keeps
 * process-lifetime metadata. */
extern "C" {
std::size_t typephp_os_runtime_arena_size()
{
    return 48u * 1024u * 1024u;
}
}

extern "C" void typephp_os_write(const char *data, size_t size)
{
    (void) syscall(SYS_write, STDOUT_FILENO, data, size);
}

extern "C" void typephp_os_panic(const char *message)
{
    static constexpr char prefix[] = "TypePHP user panic: ";
    typephp_os_write(prefix, sizeof(prefix) - 1);
    typephp_os_write(message, std::strlen(message));
    typephp_os_write("\n", 1);
    raw_exit(127);
}

extern "C" void phpx_no_exception_abort(const char *message)
{
    typephp_os_panic(message);
}

extern "C" void php_nano_host_system_time(
    std::int64_t *seconds, std::int32_t *microseconds)
{
    *seconds = static_cast<std::int64_t>(syscall(SYS_time, nullptr));
    *microseconds = 0;
}

extern "C" std::uint64_t php_nano_host_monotonic_nanoseconds()
{
    timespec value{};
    if (clock_gettime(CLOCK_MONOTONIC, &value) != 0) {
        typephp_os_panic("unable to read the monotonic clock");
    }
    return static_cast<std::uint64_t>(value.tv_sec) * UINT64_C(1000000000)
        + static_cast<std::uint64_t>(value.tv_nsec);
}

extern "C" void php_nano_host_sleep(
    std::uint64_t seconds, std::uint32_t nanoseconds)
{
    const timespec duration{
        static_cast<time_t>(seconds),
        static_cast<long>(nanoseconds),
    };
    if (nanosleep(&duration, nullptr) != 0) {
        typephp_os_panic("unable to sleep");
    }
}

extern "C" zend_result php_nano_host_random_bytes(void *bytes, std::size_t size)
{
    auto *output = static_cast<unsigned char *>(bytes);
    while (size != 0) {
        const auto result = getrandom(output, size, 0);
        if (result <= 0) {
            return FAILURE;
        }
        output += result;
        size -= static_cast<std::size_t>(result);
    }
    return SUCCESS;
}

extern "C" std::uint64_t php_nano_host_random_seed()
{
    std::uint64_t seed = 0;
    if (php_nano_host_random_bytes(&seed, sizeof(seed)) != SUCCESS) {
        typephp_os_panic("unable to obtain secure random bytes");
    }
    return seed;
}

int main(int argc, char **argv)
{
    environ = argv + argc + 1;
    php_nano_set_cli_arguments(argc, argv);
    if (php_nano_startup_composer_extensions() != SUCCESS) {
        typephp_os_panic("unable to start PHP Nano extensions");
    }
    const int status = typephp_nano_project_main();
    php_nano_shutdown_composer_extensions();
    return status;
}
