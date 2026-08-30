--TEST--
BigInt bitwise shift operations (<<, >>, <<=, >>=, bitShiftLeft, bitShiftRight)
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::bigInt("16");  // 0b10000

    // Left shift
    echo ($a << 1)->toString(); echo "\n";   // 32
    echo ($a << 3)->toString(); echo "\n";   // 128

    // Right shift
    $b = std::bigInt("128");
    echo ($b >> 1)->toString(); echo "\n";   // 64
    echo ($b >> 3)->toString(); echo "\n";   // 16

    // Right shift truncates (integer division by 2^n)
    $c = std::bigInt("15");
    echo ($c >> 1)->toString(); echo "\n";   // 7
    $negativeOdd = std::bigInt("-3");
    echo ($negativeOdd >> 1)->toString(); echo "\n"; // -2, arithmetic shift

    // Compound shift left
    $d = std::bigInt("1");
    $d <<= 10;
    echo $d->toString(); echo "\n";          // 1024

    // Compound shift right
    $e = std::bigInt("1024");
    $e >>= 5;
    echo $e->toString(); echo "\n";          // 32

    // Mixed BigInt << Int
    echo ($a << 4)->toString(); echo "\n";   // 256

    // Shift by 0
    echo ($a << 0)->toString(); echo "\n";   // 16
    echo ($a >> 0)->toString(); echo "\n";   // 16

    // Universal method calls
    echo $a->bitShiftLeft(2)->toString(); echo "\n";    // 64
    echo $b->bitShiftRight(2)->toString(); echo "\n";   // 32
}
?>
--EXPECT--
32
128
64
16
7
-2
1024
32
256
16
16
64
32
