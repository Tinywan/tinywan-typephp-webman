--TEST--
nested list() destructuring
--FILE--
<?php
function main(): void {
    $arr = [1, [2, 3], 4];
    list($a, list($b, $c), $d) = $arr;
    echo $a, PHP_EOL;
    echo $b, PHP_EOL;
    echo $c, PHP_EOL;
    echo $d, PHP_EOL;

    // deeper nesting
    $arr2 = [10, [20, [30, 40]], 50];
    list($x, list($y, list($z, $w)), $v) = $arr2;
    echo $x, PHP_EOL;
    echo $y, PHP_EOL;
    echo $z, PHP_EOL;
    echo $w, PHP_EOL;
    echo $v, PHP_EOL;
}
?>
--EXPECT--
1
2
3
4
10
20
30
40
50
