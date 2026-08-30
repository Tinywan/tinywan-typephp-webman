<?php
function main()
{
    foo();
}

function foo()
{
    bar();
}

class Obj
{
    function run()
    {
        $a = any(199);
        var_dump($a[9]);

    }
}
function bar()
{
    $o = new Obj();
    $o->run();
}