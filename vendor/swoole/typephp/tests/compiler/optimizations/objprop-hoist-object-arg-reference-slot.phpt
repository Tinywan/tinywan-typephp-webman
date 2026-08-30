--TEST--
SSA object prop: object argument can turn property slot into reference
--FILE--
<?php
use native_types;

class RefSlotFoo {
    public int $a;
}

function bind_ref(RefSlotFoo $o): void {
    $ref =& $o->a;
    $ref = 99;
}

function main(): void {
    $o = new RefSlotFoo();
    $o->a = 1;

    bind_ref($o);
    var_dump($o->a);
    $o->a += 1;

    var_dump($o->a);
}
?>
--EXPECT--
int(99)
int(100)
