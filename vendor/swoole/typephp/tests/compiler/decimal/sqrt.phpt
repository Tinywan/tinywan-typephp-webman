--TEST--
Decimal: sqrt
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("0");
    var_dump($a->sqrt()->toString());

    $b = std::decimal("1");
    var_dump($b->sqrt()->toString());

    $c = std::decimal("100");
    var_dump($c->sqrt()->toString());

    $d = std::decimal("4");
    var_dump($d->sqrt()->toString());
}
?>
--EXPECT--
string(1) "0"
string(1) "1"
string(2) "10"
string(1) "2"
