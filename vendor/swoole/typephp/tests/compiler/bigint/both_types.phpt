--TEST--
use bigint_types + use decimal_types together
--FILE--
<?php
declare(strict_types=1);
use bigint_types;
use decimal_types;

function main(): void {
    // Int literal → BigInt
    $a = 100;
    echo $a->toString(); echo "\n";

    // Float literal → Decimal
    $b = 2.5;
    echo $b->toString(); echo "\n";

    // BigInt + Int (auto BigInt)
    $c = $a + 50;
    echo $c->toString(); echo "\n";

    // Decimal + Float (auto Decimal)
    $d = $b + 1.5;
    echo $d->toString(); echo "\n";

    // BigInt + Float → should fail (can't mix)
    // But BigInt * BigInt works
    $e = $a * 3;
    echo $e->toString(); echo "\n";
}
?>
--EXPECT--
100
2.5
150
4.0
300
