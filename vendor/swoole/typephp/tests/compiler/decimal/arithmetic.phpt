--TEST--
Decimal arithmetic operations
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("100.50");
    $b = std::decimal("50.25");

    // Decimal + Decimal
    echo $a->add($b)->toString(); echo "\n";
    // Decimal - Decimal
    echo $a->sub($b)->toString(); echo "\n";
    // Decimal * Decimal
    echo $a->mul($b)->toString(); echo "\n";
    // Decimal / Decimal
    echo $a->div($b)->toString(); echo "\n";
    // Decimal mod
    $c = std::decimal("17.5");
    echo $c->mod(std::decimal("5.0"))->toString(); echo "\n";
    // Decimal neg
    echo $a->neg()->toString(); echo "\n";
    // Decimal abs
    echo $a->neg()->abs()->toString(); echo "\n";
}
?>
--EXPECT--
150.75
50.25
5050.1250
2
2.5
-100.50
100.50
