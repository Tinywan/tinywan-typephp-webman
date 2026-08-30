--TEST--
SSA object prop: object argument escape prevents property hoisting
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

function main(): void {
    $o = new Foo();
    $o->a = 1;

    make_ref($o);
    $o->a += 1;

    var_dump($o->a);
}
?>
--EXPECT--
int(100)
