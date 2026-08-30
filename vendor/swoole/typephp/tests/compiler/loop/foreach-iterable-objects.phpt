--TEST--
foreach supports PHP iterable object interfaces
--FILE--
<?php

final class NumberIterator implements Iterator
{
    private int $pos = 0;

    public function __construct(private array $items)
    {
    }

    public function rewind(): void
    {
        $this->pos = 0;
    }

    public function current(): mixed
    {
        return array_values($this->items)[$this->pos];
    }

    public function key(): mixed
    {
        return array_keys($this->items)[$this->pos];
    }

    public function next(): void
    {
        ++$this->pos;
    }

    public function valid(): bool
    {
        return $this->pos < count($this->items);
    }
}

final class ArrayAggregate implements IteratorAggregate
{
    public function __construct(private array $items)
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}

final class ArrayObjectAggregate implements IteratorAggregate
{
    public function __construct(private array $items)
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayObject($this->items);
    }
}

final class TraversableProvider
{
    public function getIterator(): Traversable
    {
        return new ArrayIterator(['m' => 'method']);
    }
}

function dump_iterable(iterable $items): void
{
    foreach ($items as $key => $value) {
        var_dump($key.':'.$value);
    }
}

function main(): void
{
    dump_iterable(['a' => 'array']);
    dump_iterable(new NumberIterator(['i' => 'iterator']));
    dump_iterable(new ArrayAggregate(['g' => 'aggregate']));
    dump_iterable(new ArrayObjectAggregate(['o' => 'arrayobject']));

    $provider = new TraversableProvider();
    foreach ($provider->getIterator() as $key => $value) {
        var_dump($key.':'.$value);
    }

    foreach ((object) ['p' => 'property'] as $key => $value) {
        var_dump($key.':'.$value);
    }
}
?>
--EXPECT--
string(7) "a:array"
string(10) "i:iterator"
string(11) "g:aggregate"
string(13) "o:arrayobject"
string(8) "m:method"
string(10) "p:property"
