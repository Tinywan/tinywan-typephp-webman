--TEST--
Test ?? operator
--FILE--
<?php

function f($x)
{
    printf("%s(%d)\n", __FUNCTION__, $x);
    return $x;
}

function main() {
    $a = f(null) ?? f(1) ?? f(2);
    var_dump($a);
}
?>
--EXPECT--
f(0)
f(1)
int(1)
