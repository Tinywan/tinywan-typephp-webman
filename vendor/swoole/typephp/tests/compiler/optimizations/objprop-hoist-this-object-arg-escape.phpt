--TEST--
SSA object prop: this object argument escape prevents property hoisting
--FILE--
<?php
use native_types;

class Foo {
    public int $a;

    public function run(): void {
        $this->a = 1;

        eval('function make_ref($o): void { $ref =& $o->a; $ref = 99; } make_ref($this);');
        $this->a += 1;

        var_dump($this->a);
    }
}

function main(): void {
    (new Foo())->run();
}
?>
--EXPECT--
int(100)
