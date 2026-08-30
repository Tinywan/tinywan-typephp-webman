<?php
function foo(): array {
    $a = 1;
    $b = 2;
    $c = 3;
    return [$a, $b, $c];
}

function main()
{
    var_dump(php_uname());
    [$a, $b, $c] = foo();
    var_dump($a, $b, $c);
}

