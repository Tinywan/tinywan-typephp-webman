<?php

use TypePhp\CompilerTest;

final class NativeScalarBinaryOperandTest extends \BaseTest
{
    public function testNativeScalarCallResultsRemainUnboxedWhenOrdered(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/native-scalar-binary-operands.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringContainsString('php::Int php_recursivenativeint(php::Int value)', $code);
        self::assertStringNotContainsString('php::Var tmp_var_', $code);
    }

    public function testPhpCompatibleScalarCallResultsRemainBoxedWhenOrdered(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/dynamic-scalar-binary-operands.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringContainsString('php::Int php_recursivephpint(php::Int value)', $code);
        self::assertStringContainsString('php::Var tmp_var_', $code);
    }
}
