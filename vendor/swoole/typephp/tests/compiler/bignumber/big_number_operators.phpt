--TEST--
BigInt and Decimal operator overloading (+, -, *, /, %, **, <, >, <=, >=, ==, !=, <=>)
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // === BigInt arithmetic operators ===
    $a = std::bigInt(100);
    $b = 200;

    // BigInt + Int
    $c = $a + $b;
    echo $c->toString(); echo "\n";
    // Int + BigInt
    $d = 300 + $a;
    echo $d->toString(); echo "\n";
    // BigInt - Int
    $e = $a - 30;
    echo $e->toString(); echo "\n";
    // BigInt * Int
    $f = $a * 5;
    echo $f->toString(); echo "\n";
    // BigInt / Int
    $g = $a / 3;
    echo $g->toString(); echo "\n";
    // BigInt % Int
    $h = $a % 7;
    echo $h->toString(); echo "\n";
    // BigInt ** Int
    $i = std::bigInt(2) ** 10;
    echo $i->toString(); echo "\n";

    // === BigInt comparison operators ===
    echo (int)($a < $b); echo "\n";
    echo (int)($a > $b); echo "\n";
    echo (int)($a <= 100); echo "\n";
    echo (int)($a >= 100); echo "\n";
    echo (int)($a == 100); echo "\n";
    echo (int)($a != 50); echo "\n";

    // BigInt <=> (spaceship)
    $sp1 = $a <=> $b;
    echo (int)$sp1; echo "\n";
    $sp2 = $a <=> 100;
    echo (int)$sp2; echo "\n";

    // === Decimal arithmetic operators ===
    $dec = std::decimal("50.25");

    // Decimal + Int
    $j = $dec + 100;
    echo $j->toString(); echo "\n";
    // Decimal - Float
    $k = $dec - 0.25;
    echo $k->toString(); echo "\n";
    // Decimal * Int
    $l = $dec * 4;
    echo $l->toString(); echo "\n";
    // Decimal / Int
    $m = $dec / 5;
    echo $m->toString(); echo "\n";

    // === Decimal comparison operators ===
    echo (int)($dec > 10); echo "\n";
    echo (int)($dec < 100); echo "\n";
    echo (int)($dec == 50.25); echo "\n";
    echo (int)($dec != 100); echo "\n";

    // Decimal <=>
    $sp3 = $dec <=> 60;
    echo (int)$sp3; echo "\n";
}
?>
--EXPECT--
300
400
70
500
33
2
1024
1
0
1
1
1
1
-1
0
150.25
50.00
201.00
10.05
1
1
1
1
-1
