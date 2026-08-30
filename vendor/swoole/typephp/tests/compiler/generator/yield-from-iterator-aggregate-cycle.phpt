--TEST--
yield from rejects an IteratorAggregate cycle
--FILE--
<?php
class CyclicAggregate implements IteratorAggregate
{
    public function getIterator(): Traversable
    {
        return $this;
    }
}

function cyclic_yield_from(): iterable
{
    yield from new CyclicAggregate();
}

function main(): void
{
    try {
        cyclic_yield_from()->current();
    } catch (Throwable $e) {
        echo get_class($e), "\n";
    }
}
?>
--EXPECT--
Exception
