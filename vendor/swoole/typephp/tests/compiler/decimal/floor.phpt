--TEST--
Decimal: floor
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("3.7");
    var_dump($a->floor()->toString());

    $b = std::decimal("-3.7");
    var_dump($b->floor()->toString());

    $c = std::decimal("5.0");
    var_dump($c->floor()->toString());

    $d = std::decimal("0.1");
    var_dump($d->floor()->toString());
}
?>
--EXPECT--
string(1) "3"
string(2) "-4"
string(1) "5"
string(1) "0"
