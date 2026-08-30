--TEST--
std::bigInt() and std::decimal() builtin functions
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // std::bigInt from big literal (auto-detected BigInt → no-op pass-through)
    $a = std::bigInt(12345678901234567890);
    echo $a->toString(); echo "\n";

    // std::bigInt from plain int
    $b = std::bigInt(42);
    echo $b->toString(); echo "\n";

    // std::bigInt arithmetic
    $c = $a->add($b);
    echo $c->toString(); echo "\n";

    // std::bigInt from string
    $d = std::bigInt("99999999999999999999");
    echo $d->toString(); echo "\n";

    // std::decimal from int
    $e = std::decimal(12345);
    echo $e->toString(); echo "\n";

    // std::decimal from string
    $f = std::decimal("3.14159265358979323");
    echo $f->toString(); echo "\n";

    // std::decimal arithmetic
    $g = $e->add($f);
    echo $g->toString(); echo "\n";

    // std::decimal from float literal (explicit, stays decimal)
    $h = std::decimal(2.5);
    echo $h->toString(); echo "\n";
}
?>
--EXPECT--
12345678901234567890
42
12345678901234567932
99999999999999999999
12345
3.14159265358979323
12348.14159265358979323
2.5
