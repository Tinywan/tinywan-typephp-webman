--TEST--
SSA object prop: multiple definitions prevent hoisting
--FILE--
<?php
use native_types;
class Foo {
    public int $a;
}
function main(): void {
    $o = new Foo();
    $o->a = 100;
    $o = new Foo();
    $o->a = 200;

    $n = 10;
    while($n--) {
        $o->a += 5;
    }
    var_dump($o->a);
}
?>
--EXPECT--
int(250)
