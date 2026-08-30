<?php

class OperatorTest extends \BaseTest
{
    public function testBooleanLiteralStrictComparisonUsesNativeBoolOperands(): void
    {
        global $translator;
        $compiler = \TypePhp\CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/../code/bool-literal-identical.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $cppFile = $compiler->convertFile($testFile);
        $cpp = file_get_contents($cppFile);

        $this->assertStringContainsString('true == pjax', $cpp);
        $this->assertStringContainsString('pjax == true', $cpp);
        $this->assertStringContainsString('false == pjax', $cpp);
        $this->assertStringContainsString('pjax == false', $cpp);
        $this->assertStringNotContainsString('php::true_ == pjax', $cpp);
        $this->assertStringNotContainsString('php::false_ == pjax', $cpp);
        $this->assertStringContainsString('php::same(php::true_, value)', $cpp);
    }

    public function testAssignedValueNotIdenticalToNullIsParenthesized(): void
    {
        global $translator;
        $compiler = \TypePhp\CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/../code/assign-not-identical-null.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $cppFile = $compiler->convertFile($testFile);
        $cpp = file_get_contents($cppFile);

        $this->assertMatchesRegularExpression(
            '/!\(php::toBool\(\(error = php::call\([^\n]+\)\)\.isNull\(\)\)\)/',
            $cpp,
        );
    }

    public function testDynamicBoolCallInLogicalExpressionIsConvertedToNativeBool(): void
    {
        global $translator;
        $compiler = \TypePhp\CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/../code/bool-dynamic-call-logical.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $cppFile = $compiler->convertFile($testFile);
        $cpp = file_get_contents($cppFile);

        // Ordered operands may be materialized before the return statement,
        // but the dynamic call result must still cross an explicit Bool
        // conversion boundary before C++ logical operators consume it.
        $this->assertStringContainsString('php::toBool(php::call(', $cpp);
    }

    public function testLiteralIntDivideByZeroDoesNotCompile(): void
    {
        $this->exec('Cannot divide or modulo by zero', 'divide-by-zero-int.php');
    }

    public function testLiteralFloatDivideByZeroDoesNotCompile(): void
    {
        $this->exec('Cannot divide or modulo by zero', 'divide-by-zero-float.php');
    }

    public function testLiteralStringDivideByZeroDoesNotCompile(): void
    {
        $this->exec('Cannot divide or modulo by zero', 'divide-by-zero-string.php');
    }

    public function testLiteralModuloByZeroDoesNotCompile(): void
    {
        $this->exec('Cannot divide or modulo by zero', 'modulo-by-zero-int.php');
    }

    public function testLiteralDivideAssignByZeroDoesNotCompile(): void
    {
        $this->exec('Cannot divide or modulo by zero', 'assign-divide-by-zero.php');
    }

    public function testLiteralModuloAssignByZeroDoesNotCompile(): void
    {
        $this->exec('Cannot divide or modulo by zero', 'assign-modulo-by-zero.php');
    }
}
