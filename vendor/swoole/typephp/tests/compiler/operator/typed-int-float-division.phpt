--TEST--
Typed int and float division follows PHP semantics (fractional result, DivisionByZeroError)
--FILE--
<?php
declare(strict_types=1);

function divInts(int $a, int $b): float
{
    return $a / $b;
}

function divFloats(float $a, float $b): float
{
    return $a / $b;
}

function main(): void
{
    var_dump(divInts(7, 2));
    var_dump(divInts(6, 3));
    var_dump(divInts(PHP_INT_MIN, -1));
    try {
        divInts(7, 0);
    } catch (DivisionByZeroError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
    var_dump(divFloats(7.0, 2.0));
    try {
        divFloats(1.5, 0.0);
    } catch (DivisionByZeroError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
float(3.5)
float(2)
float(9.223372036854776E+18)
caught: Division by zero
float(3.5)
caught: Division by zero
