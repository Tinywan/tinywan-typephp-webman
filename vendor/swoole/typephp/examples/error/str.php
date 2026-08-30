<?php
class TestObject {
    public string $a;
    function __toString(): string
    {
        return $this->a;
    }
}
function test(string $a)
{
    var_dump($a);
}

function main()
{
    $o = new TestObject();
    $o->a = "hello";
    test($o);
}

