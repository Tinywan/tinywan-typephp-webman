--TEST--
Type Declarations
--FILE--
<?php
class Foo1123 {
    public function bar() {
        var_dump(__METHOD__);
    }
}

function foo(Foo1123 $v1) {
    $v1->bar();
}

function main() {
    $o = new Foo1123;
    foo($o);
}
?>
--EXPECTF--
string(12) "Foo1123::bar"