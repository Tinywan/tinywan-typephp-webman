<?php
class ImmutableMethodCallsMutable
{
    public function mutate(): void {}

    #[Immutable]
    public function read(): void
    {
        $this->mutate();
    }
}
