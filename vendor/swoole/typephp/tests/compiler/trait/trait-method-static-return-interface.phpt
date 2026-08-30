--TEST--
Trait method with `static` return type flattened into a class that implements an interface declaring `static` return
--FILE--
<?php

interface TestInterface
{
    public function make(): static;
}

trait TestTrait
{
    public function make(): static
    {
        return new static;
    }
}

class TestClass implements TestInterface
{
    use TestTrait;
}

function main()
{
    $a = new TestClass;
    $b = $a->make();
    // `static` is late-static-bound to the consuming class (TestClass).
    var_dump($b instanceof TestClass);
    var_dump($b !== $a);
}
?>
--EXPECT--
bool(true)
bool(true)
