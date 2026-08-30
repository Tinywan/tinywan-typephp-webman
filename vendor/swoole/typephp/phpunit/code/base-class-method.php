<?php

class FooBase {
    function bar()
    {
        var_dump(__CLASS__);
    }
    function doSomething() {
        $this->bar();
    }
}

class FooChild extends FooBase {
    function bar()
    {
        var_dump(__CLASS__);
    }
}

function bar(FooBase $o)
{
    $o->doSomething();
}

function main() {
    $o = new FooChild();
    $o2 = any($o);
    bar($o2);
}
