<?php

class ImmutableClosureCapture
{
    public int $value = 1;

    public function run(#[Immutable] ImmutableClosureCapture $target): void
    {
        $callback = function () use ($target): void {
            $target->value = 2;
        };
        $callback();
    }
}
