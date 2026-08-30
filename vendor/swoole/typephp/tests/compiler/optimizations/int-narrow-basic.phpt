--TEST--
SSA narrowing: basic int operations
--FILE--
<?php
function main(): void {
    $a = 100;
    $a += 3;
    $a *= 2;
    $a -= 50;
    echo $a, PHP_EOL;

    $b = 42;
    $b %= 10;
    echo $b, PHP_EOL;

    $c = 1;
    $c++;
    $c++;
    echo $c, PHP_EOL;
}
?>
--EXPECT--
156
2
3
