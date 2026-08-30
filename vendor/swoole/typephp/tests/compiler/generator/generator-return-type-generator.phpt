--TEST--
generator return type accepts \Generator for methods, nullable and union variants
--FILE--
<?php

class Box
{
    public function gen(array $array): \Generator
    {
        foreach ($array as $value) {
            yield $value * 2;
        }
    }
}

function nullableGen(array $array): ?\Generator
{
    foreach ($array as $value) {
        yield $value;
    }
}

function unionGen(array $array): \Generator|\Iterator
{
    foreach ($array as $value) {
        yield $value;
    }
}

function main()
{
    $b = new Box();
    foreach ($b->gen([1, 2, 3]) as $v) {
        var_dump($v);
    }
    $g = nullableGen([4, 5]);
    foreach ($g as $v) {
        var_dump($v);
    }
    $u = unionGen([6, 7]);
    foreach ($u as $v) {
        var_dump($v);
    }
}
?>
--EXPECT--
int(2)
int(4)
int(6)
int(4)
int(5)
int(6)
int(7)
