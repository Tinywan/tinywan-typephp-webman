--TEST--
foreach invokes only required Iterator callbacks and cleans up exceptional cursors
--FILE--
<?php

class CallbackIterator implements Iterator
{
    public static int $keyCalls = 0;
    private int $position = 0;

    public function __construct(private string $failure = '')
    {
    }

    public function rewind(): void
    {
        if ($this->failure === 'rewind') throw new RuntimeException('rewind');
        $this->position = 0;
    }

    public function valid(): bool
    {
        if ($this->failure === 'valid') throw new RuntimeException('valid');
        return $this->position < 2;
    }

    public function current(): mixed
    {
        if ($this->failure === 'current') throw new RuntimeException('current');
        return $this->position + 10;
    }

    public function key(): mixed
    {
        ++self::$keyCalls;
        if ($this->failure === 'key') throw new RuntimeException('key');
        return $this->position;
    }

    public function next(): void
    {
        if ($this->failure === 'next') throw new RuntimeException('next');
        ++$this->position;
    }
}

final class LifetimeIterator extends CallbackIterator
{
    public static int $destroyed = 0;
    public function __destruct()
    {
        ++self::$destroyed;
    }
}

final class LifetimeAggregate implements IteratorAggregate
{
    public function getIterator(): Traversable
    {
        return new LifetimeIterator();
    }
}

function consume(string $failure, bool $withKey): void
{
    try {
        if ($withKey) {
            foreach (new CallbackIterator($failure) as $key => $value) {
            }
        } else {
            foreach (new CallbackIterator($failure) as $value) {
            }
        }
    } catch (RuntimeException $exception) {
        echo $exception->getMessage(), "\n";
    }
}

function main(): void
{
    foreach (new CallbackIterator() as $value) {
        echo $value, "\n";
    }
    var_dump(CallbackIterator::$keyCalls);

    foreach (new CallbackIterator() as $key => $value) {
    }
    var_dump(CallbackIterator::$keyCalls);

    consume('rewind', false);
    consume('valid', false);
    consume('current', false);
    consume('next', false);
    consume('key', true);

    foreach (new LifetimeAggregate() as $value) {
        break;
    }
    var_dump(LifetimeIterator::$destroyed);
}
?>
--EXPECT--
10
11
int(0)
int(2)
rewind
valid
current
next
key
int(1)
