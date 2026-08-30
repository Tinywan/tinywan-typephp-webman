--TEST--
Native class: BigInt, BigFloat and Decimal properties retain typed value semantics
--FILE--
<?php

#[Native]
class NativeHighPrecisionValues
{
    public BigInt $integer;
    public BigFloat $floating;
    public Decimal $decimal;
}

#[Native]
class NativeHighPrecisionPressure
{
    public int $value;
}

function createNativePressure(): void
{
    for ($i = 0; $i < 300000; $i++) {
        $filler = new NativeHighPrecisionPressure();
    }
}

function main(): void
{
    $values = new NativeHighPrecisionValues();
    var_dump($values->integer, $values->floating, $values->decimal);

    $values->integer = std::bigInt('123456789012345678901234567890');
    $values->floating = std::bigFloat('3.141592653589793238462643383279');
    $values->decimal = std::decimal('99.125');

    createNativePressure();

    echo ($values->integer + 10)->toString(), "\n";
    echo $values->floating->toString(), "\n";
    echo ($values->decimal * 2)->toString(), "\n";
}

?>
--EXPECT--
NULL
NULL
NULL
123456789012345678901234567900
3.141592653589793238462643383279
198.250
