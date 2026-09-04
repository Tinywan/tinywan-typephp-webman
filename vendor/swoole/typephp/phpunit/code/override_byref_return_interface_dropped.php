<?php

interface ByRefReturnContract
{
    public function &value(): array;
}

class ByRefReturnImplementation implements ByRefReturnContract
{
    public function value(): array
    {
        return [];
    }
}

function main() {}
