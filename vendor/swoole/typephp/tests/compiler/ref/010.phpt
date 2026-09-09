--TEST--
class const 001
--FILE--
<?php
function foo2(int &$v) : int {
    $v += 30;
    return $v;
}
function foo3($v1, int &$v2): int {
    $v2 *= 2;
    return $v1 + $v2;
}
function main()
{
    $i = 100;
    $f = 'foo2';
    $r = foo3($f(std::ref($i)), $i);
    var_dump($r);
}
?>
--EXPECT--
int(390)
