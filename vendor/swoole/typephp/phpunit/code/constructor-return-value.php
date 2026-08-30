<?php

class ConstructorReturnValue
{
    public function __construct()
    {
        return 123;
    }
}

function main(): void
{
    new ConstructorReturnValue();
}
