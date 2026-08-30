<?php

class ImmutableClosureThis
{
    public function mutate(): void {}

    #[Immutable]
    public function callback(): Closure
    {
        return function (): void {
            $this->mutate();
        };
    }
}
