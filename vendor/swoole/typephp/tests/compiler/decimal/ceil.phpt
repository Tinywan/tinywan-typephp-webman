--TEST--
Decimal: ceil
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("3.2");
    var_dump($a->ceil()->toString());

    $b = std::decimal("-3.2");
    var_dump($b->ceil()->toString());

    $c = std::decimal("5.0");
    var_dump($c->ceil()->toString());

    $d = std::decimal("0.1");
    var_dump($d->ceil()->toString());
}
?>
--EXPECT--
string(1) "4"
string(2) "-3"
string(1) "5"
string(1) "1"
