--TEST--
func_num_args
--FILE--
<?php

function foo1($a, $b, $c = 10)
{
    var_dump(func_num_args());
}

function foo2(...$args) {
    var_dump(func_num_args());
}

function foo3($a, $b, $c, ...$args) {
    var_dump(func_num_args());
}

function main()
{
   foo1(2.5, 3);
   foo2(1, 3, 5, 8, 10);
   foo3(1, 3, 5, 8, 10, 12);
}
?>
--EXPECT--
int(3)
int(5)
int(6)