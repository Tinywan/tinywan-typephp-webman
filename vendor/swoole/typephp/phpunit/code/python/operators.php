<?php

function main(): void
{
    $left = python\int(7);
    $right = python\int(3);

    $sum = $left + $right;
    $reverse = 10 + $right;
    $same = $left === $left;
    $different = $left !== python\int(7);
    $left += 2;

    var_dump($sum, $reverse, $same, $different, $left);
}
