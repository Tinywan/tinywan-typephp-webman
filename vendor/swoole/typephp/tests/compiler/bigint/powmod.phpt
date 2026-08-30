--TEST--
BigInt: powmod
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // 2^10 mod 1000 = 1024 mod 1000 = 24
    $a = std::bigInt(2);
    $r = $a->powmod(std::bigInt(10), std::bigInt(1000));
    echo $r->toString(); echo "\n";

    // 3^20 mod 100 = 3486784401 mod 100 = 1
    $b = std::bigInt(3);
    $r2 = $b->powmod(std::bigInt(20), std::bigInt(100));
    echo $r2->toString(); echo "\n";

    // 5^3 mod 13 = 125 mod 13 = 8
    $c = std::bigInt(5);
    $r3 = $c->powmod(std::bigInt(3), std::bigInt(13));
    echo $r3->toString(); echo "\n";
}
?>
--EXPECT--
24
1
8
