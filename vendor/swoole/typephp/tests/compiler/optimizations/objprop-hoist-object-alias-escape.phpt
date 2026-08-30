--TEST--
SSA object prop: object alias escape prevents property hoisting
--FILE--
<?php
use native_types;

class Foo {
    public int $a;
}

function make_ref(Foo $o): void {
    $ref =& $o->a;
    $ref = 99;
}

function run(Foo $right): void {
    $next = $right;
    $next->a = 1;

    make_ref($right);
    $next->a += 1;

    var_dump($next->a);
}

function main(): void {
    run(new Foo());
}
?>
--EXPECT--
int(100)
