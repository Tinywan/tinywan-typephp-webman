--TEST--
Typed int modulo and shifts follow PHP semantics (errors, boundaries)
--FILE--
<?php
declare(strict_types=1);

function modInts(int $a, int $b): int
{
    return $a % $b;
}

function shiftLeft(int $a, int $b): int
{
    return $a << $b;
}

function shiftRight(int $a, int $b): int
{
    return $a >> $b;
}

function main(): void
{
    var_dump(modInts(7, 3));
    var_dump(modInts(-7, 3));
    var_dump(modInts(PHP_INT_MIN, -1));
    try {
        modInts(7, 0);
    } catch (DivisionByZeroError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
    var_dump(shiftLeft(1, 3));
    var_dump(shiftLeft(1, 63));
    var_dump(shiftLeft(1, 64));
    try {
        shiftLeft(1, -1);
    } catch (ArithmeticError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
    var_dump(shiftRight(-8, 1));
    var_dump(shiftRight(-8, 65));
    var_dump(shiftRight(8, 65));
    try {
        shiftRight(1, -1);
    } catch (ArithmeticError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
int(1)
int(-1)
int(0)
caught: Modulo by zero
int(8)
int(-9223372036854775808)
int(0)
caught: Bit shift by negative number
int(-4)
int(-1)
int(0)
caught: Bit shift by negative number
