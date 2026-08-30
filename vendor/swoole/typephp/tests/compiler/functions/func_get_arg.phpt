--TEST--
func_get_arg
--FILE--
<?php

function foo1($a, $b, $c = 10)
{
    var_dump(func_get_arg(2));
}

function foo2(...$args) {
    var_dump(func_get_arg(3));
}

function foo3($a, $b, $c, ...$args) {
    var_dump(func_get_arg(4));
}

function main()
{
   foo1(2.5, 3);
   foo2(1, 3, 5, 8, 10);
   foo3(1, 3, 5, 8, 10, 12);
}
?>
--EXPECT--
int(10)
int(8)
int(10)