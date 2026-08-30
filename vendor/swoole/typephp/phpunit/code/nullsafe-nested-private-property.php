<?php

use native_types;

class NullsafeNestedOwner
{
    public NullsafeNestedChild $child;

    public function __construct()
    {
        $this->child = new NullsafeNestedChild();
    }
}

class NullsafeNestedChild
{
    private int $value = 1;
}

function main(): void
{
    $owner = new NullsafeNestedOwner();
    var_dump($owner?->child?->value);
}
