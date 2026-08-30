--TEST--
arrow function
--FILE--
<?php
function main()
{
    $y = 1;
    $fn1 = fn($x) => $x + $y;
    var_export($fn1(3));
}
?>
--EXPECT--
4
