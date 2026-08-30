--TEST--
Static float property native slot becomes invalid after dynamic reference binding
--FILE--
<?php
use native_types;

class StaticFloatRefSlotCrash {
    public static float $f = 1.5;
}

function main(): void {
    StaticFloatRefSlotCrash::$f = 1.5;

    eval('function bind_static_float_ref(): void { $ref =& StaticFloatRefSlotCrash::$f; $ref = 9.5; }');
    bind_static_float_ref();

    var_dump(StaticFloatRefSlotCrash::$f);
    StaticFloatRefSlotCrash::$f += 0.5;
    var_dump(StaticFloatRefSlotCrash::$f);
}
?>
--EXPECT--
float(9.5)
float(10)
