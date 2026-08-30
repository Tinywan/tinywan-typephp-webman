--TEST--
SSA: int
--FILE--
<?php
function main(): void {
    ini_set('precision', 17);
    $a = 100;
    $a += 3;
    $a *= 232;
    $a %= 3412;
    echo $a, PHP_EOL;

    $b = $a + PHP_INT_MAX;
    echo $b, PHP_EOL;
}
?>
--EXPECTF--
12
9.2233720368547758E+18