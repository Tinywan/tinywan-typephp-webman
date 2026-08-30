--TEST--
default array property
--FILE--
<?php

class Test {
    protected bool|int $attr1 = false;
    protected string $attr2 = 'hello';
    protected int $attr3 = 1232;
    protected float $attr4 = 3.1415;
    protected bool $attr5 = true;

    function bar() {
        var_dump($this->attr1, $this->attr2, $this->attr3, $this->attr4, $this->attr5);
    }
}

function main() {
    $obj = new Test;
    $obj->bar();
}
?>
--EXPECT--
bool(false)
string(5) "hello"
int(1232)
float(3.1415)
bool(true)