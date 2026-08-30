--TEST--
foreach rejects invalid IteratorAggregate getIterator return values
--FILE--
<?php

final class ArrayReturningAggregate implements IteratorAggregate
{
    public function getIterator()
    {
        return ['bad'];
    }
}

final class ObjectReturningAggregate implements IteratorAggregate
{
    public function getIterator()
    {
        return (object) ['bad' => true];
    }
}

final class NestedInvalidAggregate implements IteratorAggregate
{
    public function getIterator(): Traversable
    {
        return new ObjectReturningAggregate();
    }
}

function check(object $iterable): void
{
    try {
        foreach ($iterable as $value) {
            var_dump($value);
        }
    } catch (Throwable $e) {
        var_dump($e instanceof Exception);
        var_dump(str_contains($e->getMessage(), 'must be traversable or implement interface Iterator'));
    }
}

function main(): void
{
    check(new ArrayReturningAggregate());
    check(new ObjectReturningAggregate());
    check(new NestedInvalidAggregate());
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
