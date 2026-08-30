--TEST--
yield from forwards delegated generator values and returns its result
--FILE--
<?php
function child_gen(): iterable
{
    yield 'x' => 10;
    yield 'y' => 20;
    return 30;
}

function parent_gen(): iterable
{
    $result = yield from child_gen();
    yield 'result' => $result;
}

function main(): void
{
    $gen = parent_gen();
    foreach ($gen as $key => $value) {
        echo $key, ':', $value, "\n";
    }
}
?>
--EXPECT--
x:10
y:20
result:30
