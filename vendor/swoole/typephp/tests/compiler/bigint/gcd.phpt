--TEST--
BigInt: gcd
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::bigInt(12);
    $b = std::bigInt(8);
    echo $a->gcd($b)->toString(); echo "\n";

    $c = std::bigInt(100);
    $d = std::bigInt(25);
    echo $c->gcd($d)->toString(); echo "\n";

    $e = std::bigInt(17);
    $f = std::bigInt(13);
    echo $e->gcd($f)->toString(); echo "\n";

    // gcd with Int argument
    echo $a->gcd(4)->toString(); echo "\n";
}
?>
--EXPECT--
4
25
1
4
