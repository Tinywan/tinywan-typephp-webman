<?php

use native_types;

class NativeProtectedOwner
{
    protected int $value = 1;
}

class NativeProtectedReader
{
    public function read(NativeProtectedOwner $owner): int
    {
        return $owner->value;
    }
}

function main(): void
{
}
