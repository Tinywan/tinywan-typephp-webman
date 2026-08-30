<?php

namespace Test2;

class MyClass
{
    private string $name;
    private int $a;
    private int $b;

    function __construct(int $a, int $b, string $name)
    {
        $this->name = $name;
        $this->a = $a;
        $this->b = $b;
    }

    function sum(): int
    {
        return $this->a + $this->b;
    }
}