<?php
function main()
{
    $a = 100;
    $b = 'hello';
    $c = [12.34, true, null];

    var_dump($a, $b, ...$c);
}