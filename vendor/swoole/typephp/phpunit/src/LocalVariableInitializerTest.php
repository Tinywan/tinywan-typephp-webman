<?php

use TypePhp\CompilerTest;

final class LocalVariableInitializerTest extends \BaseTest
{
    public function testTopLevelLiteralAssignmentsInitializeDeclarations(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/local-literal-declaration-initializer.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringContainsString('php::Var integer = 42L;', $code);
        self::assertStringContainsString('php::Var negative = -7L;', $code);
        self::assertStringContainsString('php::Var floating = 1.25;', $code);
        self::assertStringContainsString('php::Var boolean = true;', $code);
        self::assertMatchesRegularExpression('/php::Str string = get_str\(\d+\);/', $code);
        self::assertStringContainsString('php::Var nullValue = php::null;', $code);

        self::assertStringContainsString('php::Var nested;', $code);
        self::assertStringContainsString('nested = 9L;', $code);
        self::assertStringContainsString('php::Var computed;', $code);
        self::assertStringContainsString('computed = ((40L) + (2L));', $code);

        $afterDeclaration = substr(
            $code,
            strpos($code, 'php::Var integer = 42L;') + strlen('php::Var integer = 42L;'),
        );
        self::assertStringNotContainsString('integer = 42L;', $afterDeclaration);
    }

    public function testNativeScalarLiteralsInitializeNativeDeclarations(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/local-literal-declaration-initializer-native.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringContainsString('php::Int integer = php::toInt(42L);', $code);
        self::assertStringContainsString('php::Int negative = php::toInt(-7L);', $code);
        self::assertStringContainsString('php::Float floating = php::toFloat(1.25);', $code);
        self::assertStringContainsString('php::Bool boolean = php::toBool(true);', $code);
        self::assertMatchesRegularExpression('/php::Str string = get_str\(\d+\);/', $code);
        self::assertStringContainsString('php::Var nullValue = php::null;', $code);
    }

    public function testOnlyCompileTimeConstantsInitializeHoistedDeclarations(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/local-constant-declaration-initializer.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringContainsString(
            'php::Var imported = _const_var_LocalConstantInitializer__Provider__LIMIT;',
            $code,
        );
        self::assertStringContainsString(
            'php::Var qualified = _const_var_LocalConstantInitializer__Provider__LABEL;',
            $code,
        );
        self::assertStringContainsString(
            'php::Var namespaced = _const_var_LocalConstantInitializer__Consumer__ENABLED;',
            $code,
        );
        self::assertStringContainsString('php::Var internal = ZEND_LONG_MAX;', $code);

        self::assertStringContainsString('php::Var runtime;', $code);
        self::assertStringContainsString('runtime = php::constant(', $code);
        self::assertStringContainsString('php::Var namespaceFallback;', $code);
        self::assertStringContainsString('namespaceFallback = php::constant(', $code);
    }

    public function testOnlyCompileTimeClassConstantsInitializeHoistedDeclarations(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/local-class-constant-declaration-initializer.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringContainsString('php::Var selfValue = get_str(', $code);
        self::assertStringContainsString('php::Var parentValue = 128L;', $code);
        self::assertStringContainsString('php::Var concreteValue = get_str(', $code);
        self::assertStringContainsString('php::Var selfClass = get_str(', $code);
        self::assertStringContainsString('php::Var parentClass = get_str(', $code);
        self::assertStringContainsString('php::Var unknownClass = get_str(', $code);

        self::assertStringContainsString('php::Var lateStatic;', $code);
        self::assertStringContainsString('lateStatic = php::constant(php::getCalledCe(this_)', $code);
        self::assertStringContainsString('php::Var external = "', $code);
        self::assertStringNotContainsString("php::Var external;\n", $code);
        self::assertStringContainsString('php::Var runtimeClassConstant;', $code);
        self::assertStringContainsString('runtimeClassConstant = php::constant(', $code);
        self::assertStringContainsString('php::Var dynamicClass;', $code);
        self::assertStringContainsString('dynamicClass = php::classConstant(', $code);
    }
}
