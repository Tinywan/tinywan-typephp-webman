--TEST--
SSA object prop: reference capture of property prevents hoisting
--FILE--
<?php
use native_types;
class Foo {
    public int $a;
}
function main(): void {
    $o = new Foo();
    $o->a = 10;

    $ref = &$o->a;
    $ref = 20;

    $n = 5;
    while($n--) {
        $o->a += 1;
    }
    var_dump($o->a);
}
?>
--EXPECT--
int(25)
