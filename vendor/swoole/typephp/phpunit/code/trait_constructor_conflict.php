<?php

declare(strict_types=1);

trait TraitA
{
    public function __construct()
    {
        echo "A\n";
    }
}

trait TraitB
{
    public function __construct()
    {
        echo "B\n";
    }
}

class TestClass
{
    use TraitA, TraitB;
}

function main()
{
    new TestClass();
}
