--TEST--
class const 001
--FILE--
<?php
function foo2(int &$v) : int {
    $v += 30;
    return $v;
}
function main()
{
    eval('$i = 100; $r = foo2($i); var_dump($r);');
}
?>
--EXPECT--
int(130)
