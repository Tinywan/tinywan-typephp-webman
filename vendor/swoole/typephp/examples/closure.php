<?php
function main(): int
{
    $a = 100;
    $b = [1, 2, 3];
    $fn = function ($x) use ($a, $b)
    {
        var_dump($a);
        var_dump($b);
        var_dump($x);
    };
    $fn(1000);
    return 0;
}
