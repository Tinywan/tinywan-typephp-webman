--TEST--
BigInt binary operators (+, -, *, /, %) and unary minus
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::bigInt(100);

    // BigInt + Int
    $b = $a + 50;
    echo $b->toString(); echo "\n";
    // Int + BigInt
    $c = 200 + $a;
    echo $c->toString(); echo "\n";
    // BigInt - Int
    $d = $a - 30;
    echo $d->toString(); echo "\n";
    // BigInt * Int
    $e = $a * 5;
    echo $e->toString(); echo "\n";
    // BigInt / Int
    $f = $a / 3;
    echo $f->toString(); echo "\n";
    // BigInt % Int
    $g = $a % 7;
    echo $g->toString(); echo "\n";

    // BigInt ** Int
    $h = std::bigInt(2) ** 10;
    echo $h->toString(); echo "\n";

    // Unary minus
    $i = -$a;
    echo $i->toString(); echo "\n";
    echo (-$i)->toString(); echo "\n";
}
?>
--EXPECT--
150
300
70
500
33
2
1024
-100
100
