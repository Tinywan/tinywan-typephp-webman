<?php

use TypePhp\CompilerTest;

final class FuncCallOptimizerTest extends BaseTest
{
    public function testStrictTypedArgumentsUseOnlyProvenDirectAbiPaths(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/func-call-optimizer-typed-arguments.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertSame(1, substr_count($code, 'php::fn::in_array('));
        self::assertSame(1, substr_count($code, 'php::fn::hypot('));
        self::assertSame(1, substr_count($code, 'php::fn::json_decode('));
        self::assertSame(1, substr_count($code, 'php::fn::floor('));
        self::assertSame(1, substr_count($code, 'php::fn::round('));
        self::assertSame(5, substr_count($code, 'php::call('));
        self::assertStringContainsString('php_optimizertypedbool()', $code);
        self::assertStringContainsString('php_optimizertypedint()', $code);
        self::assertStringContainsString('php_optimizertypedfloat()', $code);
        self::assertStringContainsString('php_optimizerdynamicbool()', $code);
        self::assertMatchesRegularExpression('/php_optimizertypedbool\(\);\s*php::fn::in_array/', $code);
        self::assertMatchesRegularExpression('/php_optimizertypedint\(\);\s*php::call/', $code);
        self::assertMatchesRegularExpression('/php_optimizerdynamicbool\(\);\s*php::call/', $code);
        self::assertStringContainsString('php::fn::hypot(php::toFloat(', $code);
        self::assertStringContainsString('php::ArgList{php::null}', $code);
        self::assertMatchesRegularExpression('/php::fn::json_decode\([^;]+php::null\);/', $code);
        self::assertStringContainsString('php::fn::floor(1.5)', $code);
        self::assertStringContainsString('php::fn::round(1.25)', $code);
    }
}
