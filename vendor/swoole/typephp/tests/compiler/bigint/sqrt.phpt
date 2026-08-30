--TEST--
BigInt: sqrt
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::bigInt(0);
    echo $a->sqrt()->toString(); echo "\n";

    $b = std::bigInt(1);
    echo $b->sqrt()->toString(); echo "\n";

    $c = std::bigInt(100);
    echo $c->sqrt()->toString(); echo "\n";

    // Integer sqrt: sqrt(2) = 1
    $d = std::bigInt(2);
    echo $d->sqrt()->toString(); echo "\n";

    // Large perfect square: 10^20, sqrt = 10^10
    $e = std::bigInt("100000000000000000000");
    echo $e->sqrt()->toString(); echo "\n";
}
?>
--EXPECT--
0
1
10
1
10000000000
