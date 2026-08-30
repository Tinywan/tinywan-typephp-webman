<?php

use native_types;

class NativePrivateOwner
{
    private int $value = 1;
}

class NativePrivateReader
{
    public function read(NativePrivateOwner $owner): int
    {
        return $owner->value;
    }
}

function main(): void
{
}
