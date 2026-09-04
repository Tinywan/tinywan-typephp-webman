<?php
abstract class A
{
    abstract public function __construct(int $x);
}

class B extends A
{
    public function __construct(string $x) {}
}

function main() {}
