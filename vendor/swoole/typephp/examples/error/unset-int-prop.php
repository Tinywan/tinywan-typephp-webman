<?php
use native_types;

class Test {
    public int $x = 100;

    function bar()
    {
        var_dump($this->x);
        unset($this->x);
        var_dump($this->x);
    }
}

function main()
{
    $o = new Test();
    $o->bar();
    var_dump($o->x);
}
