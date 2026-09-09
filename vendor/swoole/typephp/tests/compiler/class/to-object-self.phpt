--TEST--
toObject keyword method with self::class
--FILE--
<?php

class Foo {
    public function run($obj) {
        $o = $obj->toObject(self::class);
        $o->bar();
    }

    public function bar() {
        var_dump(__METHOD__);
    }
}

function main() {
    $o = new Foo();
    $o->run($o);
}

?>
--EXPECT--
string(8) "Foo::bar"
