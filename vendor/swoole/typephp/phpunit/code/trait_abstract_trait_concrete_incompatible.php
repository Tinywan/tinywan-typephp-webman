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

// The abstract requirement is collected first; the later concrete method
// replaces it and must be validated against it.
class AbstractFirst
{
    use NeedsName, HasName;
}

function main() {}
