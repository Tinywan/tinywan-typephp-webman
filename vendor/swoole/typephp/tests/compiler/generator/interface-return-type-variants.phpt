--TEST--
generator methods implementing interfaces with iterable, nullable and union return types
--FILE--
<?php
interface GenInterface
{
    public function gen(array $array): \Generator;
}

interface IterableInterface
{
    public function it(array $array): iterable;

    public function narrowed(array $array): iterable;
}

interface NullableInterface
{
    public function nullable(array $array): ?\Generator;
}

interface UnionInterface
{
    public function union(array $array): \Generator|\Iterator;
}

class Box implements GenInterface, IterableInterface, NullableInterface, UnionInterface
{
    public function gen(array $array): \Generator
    {
        foreach ($array as $value) {
            yield $value * 2;
        }
    }

    public function it(array $array): iterable
    {
        foreach ($array as $value) {
            yield $value;
        }
    }

    public function narrowed(array $array): \Generator
    {
        foreach ($array as $value) {
            yield $value;
        }
    }

    public function nullable(array $array): ?\Generator
    {
        foreach ($array as $value) {
            yield $value;
        }
    }

    public function union(array $array): \Generator|\Iterator
    {
        foreach ($array as $value) {
            yield $value;
        }
    }
}

function main()
{
    $box = new Box();
    foreach ($box->gen([1, 2, 3]) as $v) {
        var_dump($v);
    }
    foreach ($box->it([4, 5]) as $v) {
        var_dump($v);
    }
    foreach ($box->narrowed([10, 11]) as $v) {
        var_dump($v);
    }
    foreach ($box->nullable([6, 7]) as $v) {
        var_dump($v);
    }
    foreach ($box->union([8, 9]) as $v) {
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
int(10)
int(11)
int(6)
int(7)
int(8)
int(9)
