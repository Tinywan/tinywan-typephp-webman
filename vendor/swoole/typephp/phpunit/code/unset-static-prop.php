<?php

class Foo
{
    static public int $a;
}

function main(): void
{
    unset(Foo::$a);
}
