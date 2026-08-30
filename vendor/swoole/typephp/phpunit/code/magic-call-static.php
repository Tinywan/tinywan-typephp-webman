<?php

class MagicCallStaticInvalid
{
    public static function __call(string $name, array $arguments): mixed
    {
        return null;
    }
}

function main(): void
{
}
