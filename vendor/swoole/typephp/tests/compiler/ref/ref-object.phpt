--TEST--
Reference parameter with object type - property access and method call
--FILE--
<?php
class Test
{
    public int $value = 123;

    public function abc(): string
    {
        return "abc";
    }
}

function test1(Test &$test): void
{
    var_dump($test->value);
    var_dump($test->abc());
    $test->value = 456;
    $test = clone $test;
    $test->value = 789;
}

function test2(&$test)
{
    var_dump($test->value);
    var_dump($test->abc());
    $test->value = 456;
    $test = clone $test;
    $test->value = 789;
}

function main()
{
    $test = new Test();
    $testOrigin = $test;
    test1($test);
    var_dump($test !== $testOrigin);
    var_dump($testOrigin->value, $test->value);

    $test = new Test();
    $testOrigin = $test;
    test2($test);
    var_dump($test !== $testOrigin);
    var_dump($testOrigin->value, $test->value);
}
?>
--EXPECTF--
int(123)
string(3) "abc"
bool(true)
int(456)
int(789)
int(123)
string(3) "abc"
bool(true)
int(456)
int(789)
