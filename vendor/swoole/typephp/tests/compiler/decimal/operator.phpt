--TEST--
Decimal operator overloading (+, -, *, /, %) and comparisons
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $dec = std::decimal("100.25");

    // Decimal + Int
    $a = $dec + 50;
    echo $a->toString(); echo "\n";
    // Int + Decimal
    $b = 200 + $dec;
    echo $b->toString(); echo "\n";
    // Decimal - Float
    $c = $dec - 0.25;
    echo $c->toString(); echo "\n";
    // Decimal * Int
    $d = $dec * 4;
    echo $d->toString(); echo "\n";
    // Decimal / Int
    $e = $dec / 5;
    echo $e->toString(); echo "\n";
    // Decimal % Int
    $f = $dec % 7;
    echo $f->toString(); echo "\n";

    // Comparisons
    echo (int)($dec > 50); echo "\n";
    echo (int)($dec < 200); echo "\n";
    echo (int)($dec == 100.25); echo "\n";
    echo (int)($dec != 100); echo "\n";
    echo (int)($dec <= 100.25); echo "\n";
    echo (int)($dec >= 100); echo "\n";
}
?>
--EXPECT--
150.25
300.25
100.00
401.00
20.05
2.25
1
1
1
1
1
1
