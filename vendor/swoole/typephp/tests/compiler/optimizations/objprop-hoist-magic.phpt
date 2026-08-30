--TEST--
SSA object prop: __get/__set magic methods prevent hoisting
--FILE--
<?php
use native_types;
class Foo {
    public int $a;
    public function __get($name) { return $this->$name; }
    public function __set($name, $value) { $this->$name = $value; }
}
function main(): void {
    $o = new Foo();
    $o->a = 10;

    $n = 5;
    while($n--) {
        $o->a += 2;
    }
    var_dump($o->a);
}
?>
--EXPECT--
int(20)
