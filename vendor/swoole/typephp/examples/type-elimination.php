<?php
interface TestInterface
{
    public function test(): TestInterface;
}

class TestClass implements TestInterface
{
    public function test(): TestInterface
    {
        return $this;
    }

    public function foo()
    {
        var_dump(__METHOD__);
        return $this;
    }
}

function main()
{
    $test = new TestClass();
    $test = $test->test();
    $test->foo();
}