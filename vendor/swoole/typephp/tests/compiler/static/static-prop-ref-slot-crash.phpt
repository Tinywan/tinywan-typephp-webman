--TEST--
Static property native slot becomes invalid after dynamic reference binding
--FILE--
<?php
use native_types;

class StaticRefSlotCrash {
    public static int $i = 1;
}

function main(): void {
    StaticRefSlotCrash::$i = 12;

    var_dump(StaticRefSlotCrash::$i);
    eval('function bind_static_ref(): void { $ref =& StaticRefSlotCrash::$i; $ref = 99; }');
    bind_static_ref();

    var_dump(StaticRefSlotCrash::$i);
    StaticRefSlotCrash::$i += 1;
    var_dump(StaticRefSlotCrash::$i);
}
?>
--EXPECT--
int(12)
int(99)
int(100)
