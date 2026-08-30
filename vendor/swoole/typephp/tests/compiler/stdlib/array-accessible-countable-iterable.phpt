--TEST--
array or interface object checks for accessible countable iterable values
--FILE--
<?php

class AccessCountIterator implements ArrayAccess, Countable, IteratorAggregate
{
    private array $items = ['a' => 1, 'b' => 2];

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}

function check_value(mixed $value): array
{
    return [
        is_array($value) || $value instanceof ArrayAccess,
        is_array($value) || $value instanceof Countable,
        is_array($value) || $value instanceof Traversable,
    ];
}

function main(): void
{
    var_dump(check_value(['x']));
    var_dump(check_value(new AccessCountIterator()));
    var_dump(check_value(new stdClass()));
}
?>
--EXPECT--
array(3) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  bool(false)
  [1]=>
  bool(false)
  [2]=>
  bool(false)
}
