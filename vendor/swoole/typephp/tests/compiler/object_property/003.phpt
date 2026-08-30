--TEST--
default array property
--FILE--
<?php
use native_types;

class Test {
    protected int $x = 100;

    function bar() {
        $x = $this->x;
        $x += 23;
        $this->x += 12;
        var_dump($x, $this->x);
    }
}

function main() {
    $obj = new Test;
    $obj->bar();
}
?>
--EXPECT--
int(123)
int(112)