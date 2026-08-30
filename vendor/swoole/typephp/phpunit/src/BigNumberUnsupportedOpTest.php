<?php

class BigNumberUnsupportedOpTest extends \BaseTest
{
    public function testBigFloatMod()
    {
        $this->exec("Operator '%' is not supported for Big* numeric types", 'bigfloat-unsupported-mod.php');
    }

    public function testDecimalBitAnd()
    {
        $this->exec("Operator '&' is not supported for Big* numeric types", 'decimal-unsupported-bitand.php');
    }

    public function testBigFloatShiftLeft()
    {
        $this->exec("Operator '<<' is not supported for Big* numeric types", 'bigfloat-unsupported-shift.php');
    }

    public function testDecimalBitOr()
    {
        $this->exec("Operator '|' is not supported for Big* numeric types", 'decimal-unsupported-bitor.php');
    }
}
