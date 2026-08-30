--TEST--
generator re-yielding array elements via foreach with \Generator return type
--FILE--
<?php

function main()
{
    $g = test([1, 2, 3]);
    var_dump($g instanceof \Iterator);
    foreach ($g as $value)
    {
        var_dump($value);
    }
}

function test(array $array): \Generator
{
    foreach ($array as $value)
    {
        yield $value;
    }
}

// main();
?>
--EXPECT--
bool(true)
int(1)
int(2)
int(3)
