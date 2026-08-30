--TEST--
BigFloat operator overloading (+, -, *, /) and comparisons (<, >, <=, >=, ==, !=, <=>)
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // === BigFloat arithmetic operators ===
    $a = std::bigFloat(100.5);
    $b = 200;

    // BigFloat + Int
    $c = $a + $b;
    echo $c->toString(); echo "\n";
    // Int + BigFloat
    $d = 300 + $a;
    echo $d->toString(); echo "\n";
    // BigFloat - Int
    $e = $a - 30;
    echo $e->toString(); echo "\n";
    // BigFloat * Int
    $f = $a * 5;
    echo $f->toString(); echo "\n";
    // BigFloat / Int
    $g = $a / 5;
    echo $g->toString(); echo "\n";
    // BigFloat + Float
    $h = $a + 0.5;
    echo $h->toString(); echo "\n";

    // === BigFloat constructed from int ===
    $i = std::bigFloat(42);
    echo $i->toString(); echo "\n";

    // === Unary minus ===
    $neg = -$a;
    echo $neg->toString(); echo "\n";

    // === BigFloat comparison operators ===
    echo (int)($a < $b); echo "\n";
    echo (int)($a > $b); echo "\n";
    echo (int)($a <= 100.5); echo "\n";
    echo (int)($a >= 100.5); echo "\n";
    echo (int)($a == 100.5); echo "\n";
    echo (int)($a != 50); echo "\n";

    // BigFloat <=> (spaceship)
    $sp1 = $a <=> $b;
    echo (int)$sp1; echo "\n";
    $sp2 = $a <=> 100.5;
    echo (int)$sp2; echo "\n";

    // === Universal method calls ===
    $j = std::bigFloat(3.14);
    echo $j->toInt(); echo "\n";
    echo $j->abs()->toString(); echo "\n";
}
?>
--EXPECT--
300.5
400.5
70.5
502.5
20.1
101
42
-100.5
1
0
1
1
1
1
-1
0
3
3.1400000000000001
