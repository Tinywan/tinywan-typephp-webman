<?php
class Foo implements InterfaceA {
    public function foo()
    {
        return "foo";
    }
}
function main()
{
    $o = new Foo;
    echo $o->foo();
}