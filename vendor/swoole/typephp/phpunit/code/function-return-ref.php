<?php

function &test() {
    global $a;
    return $a;
}

function main()
{
    $a = null;
    $b = &test();
    $b ??= 2;

    var_dump($a, $b);
}
