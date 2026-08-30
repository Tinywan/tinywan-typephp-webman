<?php
function foo_test($a, $b, $c) {
    return 1;
}

function foo2(): void
{
    var_dump(__FUNCTION__);
    return;
    var_dump(__FUNCTION__);
}

function main()
{
    var_dump(foo_test(1, 2, 3));
    $o = new B;
    $o->foo();

    $c = new C;
    $c->offsetSet(0, 1);
    $c->offsetSet(1, 2);
    var_dump($c);
    var_dump($c->foo());

    foo2();
}
