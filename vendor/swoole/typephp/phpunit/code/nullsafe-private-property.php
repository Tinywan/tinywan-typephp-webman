<?php

use native_types;

class NullsafePrivateOwner
{
    private int $value = 1;
}

function main(): void
{
    $owner = new NullsafePrivateOwner();
    var_dump($owner?->value);
}
