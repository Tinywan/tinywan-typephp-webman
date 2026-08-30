--TEST--
Big* types: math function optimization (abs/pow/sqrt/floor/ceil/round)
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // BigInt
    $a = std::bigInt("100");
    $a1 = abs($a);
    $a2 = pow($a, 2);
    $a3 = sqrt($a);

    // Decimal
    $b = std::decimal("3.14");
    $b1 = abs($b);
    $b2 = pow($b, 3);
    $b3 = sqrt($b);
    $b4 = floor($b);
    $b5 = ceil($b);
    $b6 = round($b);
    $b7 = round(std::decimal("3.14159"), 2);

    // BigFloat
    $c = std::bigFloat("-2.5");
    $c1 = abs($c);

    echo "OK\n";
}
?>
--EXPECT--
OK
