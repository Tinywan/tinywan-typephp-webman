<?php

class BigNumericValidationTest extends \BaseTest
{
    public function testDecimalIntegerOperandDoesNotConvertThroughString(): void
    {
        global $translator;
        $compiler = \TypePhp\CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/../code/big-numeric/decimal-int-operand.php';
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $cppFile = $compiler->convertFile($testFile);
        $cpp = file_get_contents($cppFile);

        $this->assertStringContainsString(
            'php::Decimal::mul(value, php::toDecimal(1000L))',
            $cpp,
        );
        $this->assertStringNotContainsString(
            'php::toDecimal(php::toString(1000L))',
            $cpp,
        );
        $this->assertStringContainsString(
            'php::Decimal::mul(value, factor)',
            $cpp,
        );
    }

    public function testDecimalPowerOperatorIsRejected(): void
    {
        $this->exec("Operator '**' is not supported for Decimal or BigFloat", 'big-numeric/decimal-pow-operator.php');
    }

    public function testBigFloatPowerOperatorIsRejected(): void
    {
        $this->exec("Operator '**' is not supported for Decimal or BigFloat", 'big-numeric/bigfloat-pow-operator.php');
    }

    public function testDifferentBigTypesCannotBeComparedImplicitly(): void
    {
        $this->exec(
            'Cannot compare different Big* types implicitly',
            'big-numeric/mixed-big-comparison.php'
        );
    }
}
