<?php

class ImmutableConstructorTarget {}

class MutableConstructor
{
    public function __construct(ImmutableConstructorTarget $value) {}
}

function immutableConstructorParameter(#[Immutable] ImmutableConstructorTarget $value): void
{
    new MutableConstructor($value);
}
