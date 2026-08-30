--TEST--
Native class: high precision numeric properties retain their PHPX value semantics
--FILE--
<?php

#[Native]
class NativeNumericProperties
{
    public BigInt $integer;
    public BigFloat $floating;
    public Decimal $decimal;

    public function initialize(): void
    {
        $this->integer = std::bigInt('123456789012345678901234567890');
        $this->floating = std::bigFloat('3.141592653589793238462643383279');
        $this->decimal = std::decimal('199.9500');
    }
}

function main(): void
{
    $value = new NativeNumericProperties();
    $value->initialize();
    $alias = $value;

    echo $alias->integer->toString(), "\n";
    echo $alias->floating->toString(), "\n";
    echo $alias->decimal->toString(), "\n";

    $copy = clone $value;
    $copy->integer += 10;
    echo $value->integer->toString(), "\n";
    echo $copy->integer->toString(), "\n";
}
?>
--EXPECT--
123456789012345678901234567890
3.141592653589793238462643383279
199.9500
123456789012345678901234567890
123456789012345678901234567900
