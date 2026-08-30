--TEST--
SSA object prop: hoist float property to reference (NativeTypes)
--FILE--
<?php
use native_types;
class Foo {
    public float $a;
}
function main(): void {
    $o = new Foo();
    $o->a = 1.5;

    $n = 1024;
    while($n--) {
        $o->a += 0.5;
    }
    var_dump($o->a);
}
?>
--EXPECT--
float(513.5)
