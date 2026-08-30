--TEST--
SSA: int
--FILE--
<?php
use native_types;
class Foo {
    public int $b;

    function assign_add_prop($n) {
        for ($i = 0; $i < $n; ++$i) {
            $this->b += 2;
        }
    }
}
function main(): void {
    $o = new Foo();
    $o->assign_add_prop(100000);
    var_dump($o->b);
}
?>
--EXPECT--
int(200000)