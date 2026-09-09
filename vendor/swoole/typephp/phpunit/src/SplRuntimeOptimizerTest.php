<?php

use TypePhp\CompilerTest;

final class SplRuntimeOptimizerTest extends BaseTest
{
    public function testCallsUseDirectPhpxWrappersWhenArgumentTypesAreSafe(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/spl-runtime-direct-calls.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertSame(1, substr_count($code, 'php::fn::iterator_count('));
        self::assertSame(2, substr_count($code, 'php::fn::iterator_to_array('));
        self::assertSame(1, substr_count($code, 'php::fn::spl_object_hash('));
        self::assertSame(1, substr_count($code, 'php::fn::spl_object_id('));
        self::assertSame(1, substr_count($code, 'php::fn::constant('));

        // A mixed name must retain Zend's runtime parameter validation rather
        // than being coerced into the String ABI used by the direct wrapper.
        self::assertStringContainsString('php::call(', $code);
    }
}
