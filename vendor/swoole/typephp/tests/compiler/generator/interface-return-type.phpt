--TEST--
generator method implementing an interface that declares \Generator return type
--FILE--
<?php
interface T
{
    public function test(array $array): \Generator;
}

class TestClass implements T
{
    public function test(array $array): \Generator
    {
        foreach ($array as $value) {
            yield $value;
        }
    }
}

function main()
{
    $test = new TestClass;
    $g = $test->test([1, 2, 3]);
    // TypePHP generators return a \FiberGenerator which implements Iterator
    // but is NOT the Zend \Generator class.
    var_dump($g instanceof \Generator);
    var_dump($g instanceof \Iterator);
    foreach ($g as $value) {
        var_dump($value);
    }
}
?>
--EXPECT--
bool(false)
bool(true)
int(1)
int(2)
int(3)
