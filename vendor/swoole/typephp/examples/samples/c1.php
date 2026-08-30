<?php
function add(int $a, int $b): int {
    return $a + $b;
}

function main()
{
    $a = 1000;
    $b = 999;
    var_dump(add($a, $b));

    $func = 'add';
    var_dump($func($a, $b));
}
