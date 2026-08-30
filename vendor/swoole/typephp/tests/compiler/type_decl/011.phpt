--TEST--
Type Declarations
--FILE--
<?php
class Foo1 {
    public function run() {
        var_dump(__METHOD__);
    }
}

class Foo2 {
    public function run() {
        var_dump(__METHOD__);
    }
}

function main() {
    $rand = random_int(0, 10000);
    if ($rand % 2) {
        $o = any(new Foo1());
    } else {
        $o = any(new Foo2());
    }
    if (method_exists($o, 'run')) {
        $o->run();
    }
}
?>
--EXPECTF--
string(9) "Foo%d::run"