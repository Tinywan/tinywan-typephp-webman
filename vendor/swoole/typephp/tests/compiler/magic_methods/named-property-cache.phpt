--TEST--
Named magic property cache preserves polymorphism, dynamic names, and receiver evaluation
--FILE--
<?php
declare(strict_types=1);

class FirstMagicProperty
{
    private array $values = [];

    public function __get(string $name): mixed
    {
        echo "first:get:$name\n";
        return $this->values[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        echo "first:set:$name=$value\n";
        $this->values[$name] = $value;
    }
}

class SecondMagicProperty
{
    private array $values = [];

    public function __get(string $name): mixed
    {
        echo "second:get:$name\n";
        return $this->values[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        echo "second:set:$name=$value\n";
        $this->values[$name] = $value;
    }
}

#[AllowDynamicProperties]
class MaterializingMagicProperty
{
    public int $setCalls = 0;

    public function __set(string $name, mixed $value): void
    {
        $this->setCalls++;
        $this->{$name} = $value;
    }
}

final class RecursiveMagicProperty
{
    public int $getCalls = 0;

    public function __get(string $name): mixed
    {
        $this->getCalls++;
        return @$this->{$name};
    }
}

final class ThrowingMagicProperty
{
    public int $getCalls = 0;
    public int $setCalls = 0;

    public function __get(string $name): mixed
    {
        $this->getCalls++;
        if ($this->getCalls === 1) {
            throw new RuntimeException('get failed');
        }
        return 77;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setCalls++;
        if ($this->setCalls === 1) {
            throw new RuntimeException('set failed');
        }
    }
}

function readNamedProperty(object $object): mixed
{
    return $object->value;
}

function writeNamedProperty(object $object, mixed $value): void
{
    $object->value = $value;
}

function readDynamicProperty(object $object, string $name): mixed
{
    return $object->{$name};
}

function writeDynamicProperty(object $object, string $name, mixed $value): void
{
    $object->{$name} = $value;
}

function namedPropertyReceiver(object $object, int &$calls): object
{
    $calls++;
    return $object;
}

function main(): void
{
    $first = new FirstMagicProperty();
    $second = new SecondMagicProperty();

    // One generated access site sees different runtime class entries. Zend
    // must invalidate and refill the polymorphic cache rather than reusing the
    // first class's magic-property result.
    writeNamedProperty($first, 10);
    writeNamedProperty($second, 20);
    writeNamedProperty($first, 30);
    var_dump(readNamedProperty($first));
    var_dump(readNamedProperty($second));
    var_dump(readNamedProperty($first));

    // A dynamic property-name expression deliberately remains uncached.
    writeDynamicProperty($second, 'other', 40);
    var_dump(readDynamicProperty($second, 'other'));

    // __set() materializes a real dynamic property on its first invocation.
    // The cached dynamic-property sentinel must continue to dispatch through
    // Zend so the second write reaches the newly created property directly.
    $materialized = new MaterializingMagicProperty();
    writeNamedProperty($materialized, 1);
    writeNamedProperty($materialized, 2);
    var_dump($materialized->setCalls, readNamedProperty($materialized));

    // The direct TypePHP path owns the same per-name Zend recursion guard.
    // Re-reading the same missing property from __get() must not recurse.
    $recursive = new RecursiveMagicProperty();
    var_dump(readNamedProperty($recursive), $recursive->getCalls);

    // A direct magic method may throw a C++ exception. Its RAII guard must be
    // released before the next access, just as Zend clears its guard after a
    // VM-level exception.
    $throwing = new ThrowingMagicProperty();
    try {
        writeNamedProperty($throwing, 1);
    } catch (RuntimeException $e) {
        echo "set caught\n";
    }
    writeNamedProperty($throwing, 2);
    var_dump($throwing->setCalls);
    try {
        readNamedProperty($throwing);
    } catch (RuntimeException $e) {
        echo "get caught\n";
    }
    var_dump(readNamedProperty($throwing), $throwing->getCalls);

    // A parenthesized receiver is cacheable, but is still evaluated once.
    $calls = 0;
    namedPropertyReceiver($first, $calls)->value = 50;
    var_dump($calls, namedPropertyReceiver($first, $calls)->value, $calls);
}
?>
--EXPECT--
first:set:value=10
second:set:value=20
first:set:value=30
first:get:value
int(30)
second:get:value
int(20)
first:get:value
int(30)
second:set:other=40
second:get:other
int(40)
int(1)
int(2)
NULL
int(1)
set caught
int(2)
get caught
int(77)
int(2)
first:set:value=50
first:get:value
int(1)
int(50)
int(2)
