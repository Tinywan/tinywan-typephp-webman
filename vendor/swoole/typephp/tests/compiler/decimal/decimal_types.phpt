--TEST--
use decimal_types — float literals auto-converted to Decimal
--FILE--
<?php
declare(strict_types=1);
use decimal_types;

function main(): void {
    // Simple float literal → Decimal
    $a = 3.1;
    echo $a->toString(); echo "\n";

    // Float literal → Decimal + Decimal ops
    $b = 2.5;
    $c = $a->add($b);
    echo $c->toString(); echo "\n";

    // Binary op: Decimal + Float literal (auto Decimal)
    $d = $a + 1.5;
    echo $d->toString(); echo "\n";

    // Int + Float literal (auto Decimal)
    $e = 10 + 0.5;
    echo $e->toString(); echo "\n";

    // Assignment from existing Decimal
    $f = $a;
    echo $f->toString(); echo "\n";
}
?>
--EXPECT--
3.1
5.6
4.6
10.5
3.1
