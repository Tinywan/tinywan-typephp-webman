--TEST--
BigInt: toInt / toFloat
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::bigInt(42);
    var_dump($a->toInt());
    var_dump($a->toFloat());

    $b = std::bigInt(-100);
    var_dump($b->toInt());
}
?>
--EXPECT--
int(42)
float(42)
int(-100)
