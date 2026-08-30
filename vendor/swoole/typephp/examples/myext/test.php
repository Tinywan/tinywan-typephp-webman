<?php
function my_add(int $a, int $b): int
{
    $env = $_ENV;
    var_dump(count($env));
    return $a + $b;
}

function main()
{
    var_dump(my_add(1, 2));
}