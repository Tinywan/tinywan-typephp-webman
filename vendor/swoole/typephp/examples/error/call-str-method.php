<?php
function bar(): stdClass
{
    return new stdClass();
}
function foo(string $o)
{
    $o2 = bar();
    $o2->call();
}

function main()
{
    foo("hello");
}
