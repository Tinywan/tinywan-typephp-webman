<?php
class C extends ArrayObject
{
    // Exact parameter type, covariant return over the tentative mixed.
    public function offsetGet(mixed $key): string
    {
        return 'x';
    }

    // Tentative int return: Zend only deprecates a mismatch, never fatals.
    public function count(): string
    {
        return 'x';
    }
}

class D extends Exception
{
    // Real string return type, matched exactly.
    public function __toString(): string
    {
        return 'x';
    }
}

class E extends ArrayObject
{
    // Trailing variadic absorbing both offsetSet() parameters.
    public function offsetSet(mixed ...$args): void {}
}

function main() {}
