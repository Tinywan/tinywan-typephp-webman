--TEST--
Interface return type covariance with nullable interface and anonymous class
--FILE--
<?php

declare(strict_types=1);

interface TestInterface1
{
}

interface TestInterface2 extends TestInterface1
{
}

interface TestInterface3
{
    public function test(): ?TestInterface1;
}

class TestClass implements TestInterface3
{
    // Covariant: ?TestInterface2 is a subtype of ?TestInterface1 because
    // TestInterface2 extends TestInterface1.
    public function test(): ?TestInterface2
    {
        return new class() implements TestInterface2 {
            public function hello(): string {
                return "anon";
            }
        };
    }
}

function main()
{
    $test = new TestClass;
    $result = $test->test();
    var_dump($result instanceof TestInterface1);
    var_dump($result instanceof TestInterface2);
    var_dump($result === null);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
