<?php

use TypePhp\CompilerBase;
use TypePhp\CompilerTest;

final class PropertyCacheCodegenTest extends BaseTest
{
    public function testRequestCachesUseOneTlsPointerAndRequestLifetimeStorage(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $compiler->setBuildMode(CompilerBase::BUILD_MODE_EXT);
        $compiler->setTargetName('request_cache_storage');
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/property-cache-sites.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $compiler->convertFile($source);
        $extension = file_get_contents($compiler->genExtension());

        self::assertIsString($extension);
        self::assertStringContainsString('#include <new>', $extension);
        self::assertStringContainsString('struct php_request_cache_storage final {', $extension);
        self::assertStringContainsString('zend_class_entry *class_map[', $extension);
        self::assertStringContainsString('zend_function *func_map[', $extension);
        self::assertStringContainsString('php::PropertyCacheSlot property_cache_map[', $extension);
        self::assertStringContainsString(
            'static THREAD_LOCAL php_request_cache_storage *php_request_cache = nullptr;',
            $extension,
        );
        self::assertStringContainsString(
            'php_request_cache = new (std::nothrow) php_request_cache_storage{};',
            $extension,
        );
        self::assertStringContainsString('delete php_request_cache;', $extension);
        self::assertStringContainsString('php_request_cache = nullptr;', $extension);

        self::assertStringNotContainsString('static THREAD_LOCAL zend_class_entry *php_class_map[', $extension);
        self::assertStringNotContainsString('static THREAD_LOCAL zend_function *php_func_map[', $extension);
        self::assertStringNotContainsString(
            'static THREAD_LOCAL php::PropertyCacheSlot php_property_cache_map[',
            $extension,
        );
        self::assertStringNotContainsString('std::memset(php_class_map', $extension);
        self::assertStringNotContainsString('std::memset(php_func_map', $extension);

        // Module-lifetime caches remain statically allocated and do not enter TLS.
        self::assertStringContainsString(
            'static php::PersistentCacheSlot<zend_class_entry *> php_persistent_class_map[',
            $extension,
        );
        self::assertStringContainsString(
            'static php::PersistentCacheSlot<zend_function *> php_persistent_func_map[',
            $extension,
        );
        self::assertStringContainsString(
            'static php::PersistentCacheSlot<uint32_t> php_persistent_property_map[',
            $extension,
        );
    }

    public function testOnlyStaticallyNamedPropertySitesReceiveZendCacheSlots(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/property-cache-sites.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertSame(1, substr_count($code, 'typephp_read_property_cached('));
        self::assertSame(2, substr_count($code, 'typephp_write_property_cached('));
        self::assertStringContainsString('.attr(name, php::AttrMode::Get)', $code);
        self::assertStringContainsString('typephp_write_property_scoped(object, name, value', $code);
        self::assertSame(1, substr_count($code, 'typephp_read_magic_property_direct('));
        self::assertSame(1, substr_count($code, 'typephp_write_magic_property_direct('));
    }
}
