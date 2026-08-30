--TEST--
SSA: int
--FILE--
<?php
class Foo {
    public int $a;
}
function main(): void {
    $o = new Foo();
    $o->a = 12;

    $n = 1024;
    while($n--) {
        $o->a += 13;
    }
    var_dump($o->a);
}
?>
--EXPECT--
int(13324)