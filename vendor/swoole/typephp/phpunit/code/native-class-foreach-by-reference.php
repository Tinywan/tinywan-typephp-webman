<?php

#[Native]
class NativeReferenceIterator implements Iterator
{
    public function rewind(): void {}
    public function valid(): bool { return false; }
    public function current(): mixed { return null; }
    public function key(): mixed { return null; }
    public function next(): void {}
}

function main(): void
{
    $iterator = new NativeReferenceIterator();
    foreach ($iterator as &$value) {
    }
}
