--TEST--
Constant bit shift boundaries follow PHP semantics
--FILE--
<?php
declare(strict_types=1);

function main(): void
{
    var_dump(1 << 64);
    var_dump(1 >> 64);
    var_dump(-1 >> 64);
    var_dump(PHP_INT_MIN >> 64);
    var_dump(1 >> 63);
    var_dump(-1 >> 63);
    var_dump(1 << 63);
    var_dump(5 >> 1);
    var_dump(1 >> (32 + 32));
    try {
        var_dump(1 >> -1);
    } catch (ArithmeticError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
}
?>
--EXPECTF--
int(0)
int(0)
int(-1)
int(-1)
int(0)
int(-1)
int(-9223372036854775808)
int(2)
int(0)
caught: Bit shift by negative number
