<?php
function foo(int $a, int $b): int {
    return $a + $b;
}

class A {
    function foo(int|float $a, int|float $b): int
    {
        return $a + $b;
    }
}


//foo(1, 2);
//max(1, 2, 3);
function main()
{
    $o = new A;
    $o->foo(1, 2);
    var_dump($xxx);
//    var_dump($arr2['hello']);
//    var_dump($o2->null);
}
