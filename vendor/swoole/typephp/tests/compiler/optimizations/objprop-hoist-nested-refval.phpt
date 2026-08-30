--TEST--
SSA object prop: nested refval property use prevents hoisting
--FILE--
<?php
use native_types;

class Foo {
    public int $a;
}

function mutate(&$value): int {
    $value = 20;
    return 1;
}

function main(): void {
    $o = new Foo();
    $o->a = 10;

    $ignored = mutate(refval($o->a));
    $o->a += 5;

    var_dump($o->a);
}
?>
--EXPECT--
int(25)
