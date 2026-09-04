<?php
trait NeedsName
{
    abstract public function name(int $id): string;
}

trait HasName
{
    public function name(string $id): string
    {
        return $id;
    }
}

// The concrete method is collected first; the later abstract requirement is
// dropped in its favor but must still be satisfied by it.
class ConcreteFirst
{
    use HasName, NeedsName;
}

function main() {}
