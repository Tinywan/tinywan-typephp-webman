--TEST--
Decimal: pow
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("2");
    var_dump($a->pow(std::decimal("3"))->toString());

    $b = std::decimal("10");
    var_dump($b->pow(std::decimal("2"))->toString());

    $c = std::decimal("3");
    var_dump($c->pow(std::decimal("3"))->toString());
}
?>
--EXPECT--
string(1) "8"
string(3) "100"
string(2) "27"
