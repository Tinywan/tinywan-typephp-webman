--TEST--
object link operator
--FILE--
<?php
function main()
{
    $a = [1, 2, 3];
    $b = &$a;
    $b[] = 5;

    $c = &$b;
    $c [] = 6;

    assert(count($a) === 5);
    assert(count($b) === 5);
    assert(count($c) === 5);
}
?>
--EXPECT--
