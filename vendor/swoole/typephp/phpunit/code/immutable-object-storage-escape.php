<?php

class ImmutableStorageEscapeTarget {}

class ImmutableStorageEscape
{
    public ?ImmutableStorageEscapeTarget $stored = null;

    public function store(#[Immutable] ImmutableStorageEscapeTarget $value): void
    {
        $this->stored = $value;
    }
}
