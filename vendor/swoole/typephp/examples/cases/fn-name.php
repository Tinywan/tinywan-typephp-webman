<?php

function cube($n)
{
    return ($n * $n * $n);
}

function main() 
{
    $a = [1, 2, 3, 4, 5];
    $b = array_map(cube, $a);
    print_r($b);
}


