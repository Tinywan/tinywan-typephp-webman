<?php


trait TestTrait
{
    private function __construct()
    {
        echo "trait ctor\n";
    }
}

class TestClass
{
    use TestTrait;
}

function main()
{
    new TestClass();
}
