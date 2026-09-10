<?php

class UniversalMethodCallTest extends \BaseTest
{
    public function testUnknownMethodOnInt()
    {
        $this->exec("Cannot call method `unknownMethod()` on variable of type php::Int", 'universal-method-int-undefined.php');
    }

    public function testAddWithWrongArgCount()
    {
        $this->exec('Method `add()` expects exactly 1 argument(s), 0 given', 'universal-method-int-wrong-args.php');
    }

    public function testMutatingMethodOnNonVarExpr()
    {
        $this->exec('Cannot call mutating method `push()` on a non-variable expression', 'universal-method-mutating-expr.php');
    }

    public function testVoidMethodCall()
    {
        $this->compile('void-method-call.php');
    }

    public function testHighPrecisionToBoolUsesNumericConversion(): void
    {
        global $translator;
        $compiler = \TypePhp\CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/universal-method-high-precision-to-bool.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertStringContainsString('php::BigInt::toBool(', $code);
        self::assertStringContainsString('php::BigFloat::toBool(', $code);
        self::assertStringContainsString('php::Decimal::toBool(', $code);
        self::assertStringContainsString('php::toBigFloat(php::toInt(1L))', $code);
    }

}
