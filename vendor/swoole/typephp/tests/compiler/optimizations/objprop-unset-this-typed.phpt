--TEST--
SSA object prop: unset typed this property disables property slot hoisting
--FILE--
<?php
use native_types;

class Foo {
    public int $a = 7;

    public function run(): void {
        var_dump(isset($this->a));
        unset($this->a);
        var_dump(isset($this->a));
        var_dump($this->a);
        $this->a = 11;
        var_dump($this->a);
    }
}

function main(): void {
    $foo = new Foo();
    $foo->run();
}
?>
--EXPECT--
bool(true)
bool(true)
int(7)
int(11)
