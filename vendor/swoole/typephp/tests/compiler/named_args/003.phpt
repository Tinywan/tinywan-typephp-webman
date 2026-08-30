--TEST--
named args
--FILE--
<?php
function foo($a, $b, $c): void
{
    var_dump($a, $b, $c);
}

function main(): void
{
    foo(c: 3, a: 1, b: 2);
}
?>
--EXPECT--
int(1)
int(2)
int(3)
