<?php
class Foo {
    static public function func1(string $name)
    {
        var_dump('hello ' . $name . '!');
    }

    public function func2(string $name)
    {
        var_dump('hello ' . $name . '!');
    }
}

function main()
{
    Foo::func1('world');

    $obj = new Foo();
    $obj->func2('php');
}
