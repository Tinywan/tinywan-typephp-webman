<?php

class MagicCallStaticNonStaticInvalid
{
    public function __callStatic(string $name, array $arguments): mixed
    {
        return null;
    }
}

function main(): void
{
}
