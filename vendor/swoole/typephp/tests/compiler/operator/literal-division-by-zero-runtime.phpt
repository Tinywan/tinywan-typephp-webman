--TEST--
Literal zero divisors compile and raise catchable DivisionByZeroError at runtime
--FILE--
<?php
declare(strict_types=1);

function main(): void
{
    $cond = false;
    if ($cond) {
        $x = 1 % 0;
        var_dump($x);
    }
    echo "dead code ok\n";

    try {
        $y = 10 / 0;
        var_dump($y);
    } catch (DivisionByZeroError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }

    try {
        $f = 1.0 / 0.0;
        var_dump($f);
    } catch (DivisionByZeroError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }

    $v = 10;
    try {
        $v /= 0;
    } catch (DivisionByZeroError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
    var_dump($v);

    $w = 10;
    try {
        $w %= 0;
    } catch (DivisionByZeroError $e) {
        echo "caught: " . $e->getMessage() . "\n";
    }
    var_dump($w);
}
?>
--EXPECT--
dead code ok
caught: Division by zero
caught: Division by zero
caught: Division by zero
int(10)
caught: Modulo by zero
int(10)
