--TEST--
objval
--FILE--
<?php

class Foo {
    public function run($obj) {
        $o = objval($obj, self::class);
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
