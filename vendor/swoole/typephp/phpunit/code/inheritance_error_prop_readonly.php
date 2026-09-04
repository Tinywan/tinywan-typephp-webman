<?php
class A
{
    public int $x = 1;
}

class B extends A
{
    public readonly int $x;
}

function main() {}
