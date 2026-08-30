--TEST--
Decimal: round
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // round without precision (default 0, round to integer)
    $a = std::decimal("3.4");
    var_dump($a->round()->toString());

    $b = std::decimal("3.5");
    var_dump($b->round()->toString());

    $c = std::decimal("-3.5");
    var_dump($c->round()->toString());

    // round with precision
    $d = std::decimal("3.14159");
    var_dump($d->round(2)->toString());

    $e = std::decimal("2.71828");
    var_dump($e->round(3)->toString());
}
?>
--EXPECT--
string(1) "3"
string(1) "4"
string(2) "-4"
string(4) "3.14"
string(5) "2.718"
