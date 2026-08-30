<?php

class ImmutableOverrideParent
{
    #[Immutable]
    public function read(): int
    {
        return 1;
    }
}

class ImmutableOverrideChild extends ImmutableOverrideParent
{
    public function read(): int
    {
        return 2;
    }
}
