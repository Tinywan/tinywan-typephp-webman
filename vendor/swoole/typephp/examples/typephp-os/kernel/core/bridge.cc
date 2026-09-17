#include <typephp_os_abi.h>
#include <php_nano_extension.h>
#include <phpx.h>
#include <phpx_helper.h>
#include <cstdint>

void php_main();

extern "C" {
#include <zend_exceptions.h>
}

extern "C" void kernel_clear_c(int color);
extern "C" void kernel_put_char_c(int ascii, int color);
extern "C" void kernel_halt_c();
extern "C" unsigned long physical_memory_megabytes();
extern "C" unsigned long physical_chunk_smoke_test();
extern "C" int typephp_os_disk_available();
extern "C" int typephp_os_disk_read_sector(uint32_t lba, unsigned char *data);
extern "C" int typephp_os_disk_write_sector(uint32_t lba, const unsigned char *data);
extern "C" int typephp_os_disk_flush();
extern "C" unsigned long typephp_os_disk_cache_hits();
extern "C" unsigned long typephp_os_disk_cache_misses();
extern "C" void typephp_os_process_start();

extern "C" ZEND_NORETURN void phpx_no_exception_abort(const char *fallback)
{
    if (EG(exception) != nullptr) {
        zval value;
        zval *message = zend_read_property_ex(
            zend_get_exception_base(EG(exception)),
            EG(exception),
            ZSTR_KNOWN(ZEND_STR_MESSAGE),
            true,
            &value);
        typephp_os_write("Uncaught ", sizeof("Uncaught ") - 1);
        typephp_os_write(ZSTR_VAL(EG(exception)->ce->name), ZSTR_LEN(EG(exception)->ce->name));
        typephp_os_write(": ", sizeof(": ") - 1);
        if (Z_TYPE_P(message) == IS_STRING) {
            typephp_os_write(Z_STRVAL_P(message), Z_STRLEN_P(message));
        } else {
            typephp_os_write(fallback, strlen(fallback));
        }
        typephp_os_write("\n", 1);
    }
    typephp_os_panic(fallback);
}

static void exception_model_smoke_test()
{
    php::enableDebugInfo(true);
    php::pushDebugFrame("kernel-smoke.php", 42, "Kernel::smoke");
    zend_throw_exception_ex(zend_ce_type_error, 0, "%s", "kernel exception");
    if (EG(exception) == nullptr || EG(exception)->ce != zend_ce_type_error) {
        typephp_os_panic("Zend TypeError construction failed");
    }

    php::augmentException();
    zval value;
    zval *file = zend_read_property_ex(
        zend_get_exception_base(EG(exception)), EG(exception), ZSTR_KNOWN(ZEND_STR_FILE), true, &value);
    if (Z_TYPE_P(file) != IS_STRING || !zend_string_equals_literal(Z_STR_P(file), "kernel-smoke.php")) {
        typephp_os_panic("PHPX exception augmentation failed");
    }

    zend_clear_exception();
    php::popDebugFrame();
    php::enableDebugInfo(false);
}

extern "C" void typephp_kernel_main()
{
    if (php_nano_startup_composer_extensions() != SUCCESS) {
        typephp_os_panic("Unable to start Nano extensions");
    }
    exception_model_smoke_test();
    php_main();
}

void php_kernel_clear(php::Int color)
{
    kernel_clear_c(static_cast<int>(color));
}

void php_kernel_put_char(php::Int ascii, php::Int color)
{
    kernel_put_char_c(static_cast<int>(ascii), static_cast<int>(color));
}

void php_kernel_write(php::Str text, php::Int color)
{
    for (size_t i = 0; i < text.length(); ++i) {
        kernel_put_char_c(static_cast<unsigned char>(text.data()[i]), static_cast<int>(color));
    }
}

php::Int php_kernel_word_bits()
{
    return static_cast<php::Int>(sizeof(php::Int) * 8);
}

php::Int php_kernel_memory_megabytes()
{
    return static_cast<php::Int>(physical_memory_megabytes());
}

php::Int php_kernel_chunk_smoke_test()
{
    return static_cast<php::Int>(physical_chunk_smoke_test());
}

void php_kernel_halt()
{
    kernel_halt_c();
}

php::Bool php_kernel_disk_available()
{
    return typephp_os_disk_available() != 0;
}

php::Str php_kernel_disk_read_sector(php::Int lba)
{
    unsigned char data[512];
    if (lba < 0 || lba > UINT32_MAX
        || !typephp_os_disk_read_sector(static_cast<uint32_t>(lba), data)) {
        return php::Str();
    }
    return php::Str(reinterpret_cast<const char *>(data), sizeof(data));
}

php::Bool php_kernel_disk_write_sector(php::Int lba, php::Str data)
{
    return lba >= 0 && lba <= UINT32_MAX && data.length() == 512
        && typephp_os_disk_write_sector(
            static_cast<uint32_t>(lba),
            reinterpret_cast<const unsigned char *>(data.data())) != 0;
}

php::Bool php_kernel_disk_flush()
{
    return typephp_os_disk_flush() != 0;
}

php::Int php_kernel_disk_cache_hits()
{
    return static_cast<php::Int>(typephp_os_disk_cache_hits());
}

php::Int php_kernel_disk_cache_misses()
{
    return static_cast<php::Int>(typephp_os_disk_cache_misses());
}

void php_kernel_process_start()
{
    typephp_os_process_start();
}
