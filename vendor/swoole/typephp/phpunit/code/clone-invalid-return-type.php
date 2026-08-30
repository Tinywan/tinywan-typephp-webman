<?php

class CloneInvalidReturnType
{
    public function __clone(): int
    {
        return 1;
    }
}

function main(): void
{
}
