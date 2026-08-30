<?php

class ImmutableGeneratorTarget
{
    public function mutate(): void {}
}

function immutableGeneratorContext(#[Immutable] ImmutableGeneratorTarget $target): Generator
{
    yield 1;
    $target->mutate();
}
