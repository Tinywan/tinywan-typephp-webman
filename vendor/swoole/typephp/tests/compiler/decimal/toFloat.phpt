--TEST--
Decimal: toFloat
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("3.14");
    var_dump($a->toFloat());

    $b = std::decimal("100.5");
    var_dump($b->toFloat());
}
?>
--EXPECT--
float(3.14)
float(100.5)
