--TEST--
Decimal multiplication accepts Int and dynamically typed Int operands
--FILE--
<?php

function multiplyDecimal($factor): string
{
    $value = std::decimal('123.456');
    $value = $value * $factor;
    return $value->toString();
}

function main(): void
{
    $value = std::decimal('123.456');
    echo ($value * 1000)->toString(), "\n";
    echo multiplyDecimal(1000), "\n";
}
?>
--EXPECT--
123456.000
123456.000
