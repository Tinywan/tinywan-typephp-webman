<?php

namespace TypePhp\Build;

use ArrayAccess;
use LogicException;

/** @implements ArrayAccess<string, mixed> */
abstract class CommandOptions implements ArrayAccess
{
    final public function __construct(protected readonly array $values)
    {
    }

    public function toArray(): array { return $this->values; }
    public function offsetExists(mixed $offset): bool { return isset($this->values[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->values[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): never { throw new LogicException('Command options are immutable'); }
    public function offsetUnset(mixed $offset): never { throw new LogicException('Command options are immutable'); }
}
