--TEST--
default array property
--FILE--
<?php
use native_types;

class Test {
    protected int $x = 100;

    function bar() {
        $n = 12345;
        while($n--) {
            $this->x += $n;
        }
        var_dump($this->x);
    }
}

function main() {
    $obj = new Test;
    $obj->bar();
}
?>
--EXPECT--
int(76193440)