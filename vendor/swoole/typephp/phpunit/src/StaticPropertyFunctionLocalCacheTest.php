<?php

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

class StaticPropertyFunctionLocalCacheTest extends BaseTest
{
    public function testSlotsAndCalledScopeAreGeneratedOnlyForFunctionsThatUseThem(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/static-property-function-local-cache.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        try {
            $output = $compiler->convertFile($source);
        } catch (TestError $error) {
            self::fail($error->getMessage());
        }
        $code = file_get_contents($output);
        self::assertIsString($code);

        self::assertMatchesRegularExpression(
            '/php_staticpropertyfunctionlocalcache__nostaticproperty\(.*?return php::toInt\(1L\);\n}/s',
            $code,
        );
        preg_match(
            '/php_staticpropertyfunctionlocalcache__nostaticproperty\(.*?\n}/s',
            $code,
            $noStatic,
        );
        self::assertStringNotContainsString('_typephp_static_property_slot_', $noStatic[0]);
        self::assertStringNotContainsString('_typephp_called_ce', $noStatic[0]);

        preg_match('/php_staticpropertyfunctionlocalcache__repeatedself\(.*?\n}/s', $code, $selfMethod);
        self::assertSame(1, substr_count($selfMethod[0], 'zval *_typephp_static_property_slot_0 = nullptr;'));
        self::assertSame(1, substr_count($selfMethod[0], 'const auto _typephp_static_property_0'));
        self::assertSame(1, substr_count($selfMethod[0], 'typephp_get_static_property_cached('));
        self::assertSame(3, substr_count($selfMethod[0], '_typephp_static_property_0()'));
        self::assertSame(1, substr_count($selfMethod[0], 'typephp_get_static_property_slot(get_persistent_class('));
        self::assertStringNotContainsString('_typephp_called_ce', $selfMethod[0]);

        preg_match('/php_staticpropertyfunctionlocalcache__repeatedstatic\(.*?\n}/s', $code, $staticMethod);
        self::assertSame(1, substr_count($staticMethod[0], 'typephp_get_called_ce(this_)'));
        self::assertSame(1, substr_count($staticMethod[0], 'typephp_get_called_class(_typephp_called_ce)'));
        self::assertSame(1, substr_count($staticMethod[0], 'zval *_typephp_static_property_slot_0 = nullptr;'));
        self::assertSame(1, substr_count($staticMethod[0], 'const auto _typephp_static_property_0'));
        self::assertSame(1, substr_count($staticMethod[0], 'typephp_get_static_property_cached('));
        self::assertSame(3, substr_count($staticMethod[0], '_typephp_static_property_0()'));
        self::assertSame(1, substr_count($staticMethod[0], 'typephp_get_static_property_slot(_typephp_called_ce'));
    }
}
