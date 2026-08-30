--TEST--
SSA object prop: loop body object redefinition prevents hoisting
--FILE--
<?php
use native_types;

class Foo {
    public int $a;
}

function readFoo(Foo $foo): int {
    return $foo->a;
}

function main(): void {
    $o = new Foo();
    $o->a = 1;

    $n = 1;
    while ($n--) {
        $o = new Foo();
        $o->a = 5;
    }

    $o->a += 1;
    var_dump(readFoo($o));
}
?>
--EXPECT--
int(6)
