<?php

class MagicGetProtectedInvalid
{
    protected function __get(string $name): mixed
    {
        return null;
    }
}

function main(): void
{
}
