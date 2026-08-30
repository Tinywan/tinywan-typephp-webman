--TEST--
Decimal: powmod
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // 2^10 mod 1000 = 24
    $a = std::decimal("2");
    $r = $a->powmod(std::decimal("10"), std::decimal("1000"));
    var_dump($r->toString());

    // 3^4 mod 5 = 81 mod 5 = 1
    $b = std::decimal("3");
    $r2 = $b->powmod(std::decimal("4"), std::decimal("5"));
    var_dump($r2->toString());
}
?>
--EXPECT--
string(2) "24"
string(1) "1"
