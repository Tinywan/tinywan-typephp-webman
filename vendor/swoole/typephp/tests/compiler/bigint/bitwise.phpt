--TEST--
BigInt bitwise operations (&, |, ^, ~, &=, |=, ^=, testBit, popCount)
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::bigInt("240");  // 0xF0
    $b = std::bigInt("15");   // 0x0F

    // Bitwise AND
    $r1 = $a & $b;
    echo ($a & $b)->toString(); echo "\n";

    // Bitwise OR
    echo ($a | $b)->toString(); echo "\n";

    // Bitwise XOR
    echo ($a ^ $b)->toString(); echo "\n";

    // Bitwise NOT
    echo (~$a)->toString(); echo "\n";

    // testBit
    echo $a->testBit(7)->toString(); echo "\n";  // bit 7 = 1
    echo $a->testBit(3)->toString(); echo "\n";  // bit 3 = 0
    echo $a->testBit(0)->toString(); echo "\n";  // bit 0 = 0

    // popCount
    echo $a->popCount()->toString(); echo "\n";  // 0xF0 has 4 ones

    // Compound AND
    $c = std::bigInt("255");  // 0xFF
    $c &= std::bigInt("15");  // 0x0F
    echo $c->toString(); echo "\n";

    // Compound OR
    $d = std::bigInt("240");  // 0xF0
    $d |= std::bigInt("15");  // 0x0F
    echo $d->toString(); echo "\n";

    // Compound XOR
    $e = std::bigInt("255");  // 0xFF
    $e ^= std::bigInt("170"); // 0xAA
    echo $e->toString(); echo "\n";

    // Mixed BigInt & Int
    echo ($a & 0x0F)->toString(); echo "\n";

    // Universal method calls
    echo $a->bitAnd($b)->toString(); echo "\n";
    echo $a->bitOr($b)->toString(); echo "\n";
    echo $a->bitXor($b)->toString(); echo "\n";
    echo $a->bitNot()->toString(); echo "\n";
}
?>
--EXPECT--
0
255
255
-241
1
0
0
4
15
255
85
0
0
255
255
-241
