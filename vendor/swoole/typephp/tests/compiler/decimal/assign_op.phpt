--TEST--
Decimal compound assignment operators (+=, -=, *=, /=, %=)
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // Decimal +=
    $a = std::decimal("100.50");
    $a += 25.25;
    echo $a->toString(); echo "\n";

    // Decimal += with int
    $a += 100;
    echo $a->toString(); echo "\n";

    // Decimal -=
    $b = std::decimal("500.00");
    $b -= 123.45;
    echo $b->toString(); echo "\n";

    // Decimal *=
    $c = std::decimal("50.5");
    $c *= 2;
    echo $c->toString(); echo "\n";

    // Decimal /=
    $d = std::decimal("100.00");
    $d /= 4;
    echo $d->toString(); echo "\n";

    // Decimal %=
    $e = std::decimal("17.5");
    $e %= 5.0;
    echo $e->toString(); echo "\n";
}
?>
--EXPECT--
125.75
225.75
376.55
101.0
25.00
2.5
