--TEST--
unset typed property via dynamic code
--FILE--
<?php

class FooObject {
    public int $value = 42;
}

function main() {
    $base = new FooObject();
    $base->value += 1;
    var_dump($base->value);

    eval('function test(FooObject $obj) {
        unset($obj->value);
    }');

    test($base);
    var_dump($base->value);

    echo "done\n";
}
?>
--EXPECT--
int(43)
int(0)
done