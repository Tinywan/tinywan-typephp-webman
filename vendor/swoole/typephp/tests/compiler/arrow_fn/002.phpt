--TEST--
arrow function 2
--FILE--
<?php
function main()
{
    $array = [1, 5, 9];
    $fn1 = fn() => var_dump(0, ...$array);
    $fn1();
}
?>
--EXPECT--
int(0)
int(1)
int(5)
int(9)

