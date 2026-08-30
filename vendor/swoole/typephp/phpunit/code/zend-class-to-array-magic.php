<?php

class ZendMagicToArray
{
    public function __call(string $name, array $arguments): array
    {
        return [$name];
    }
}

function main(): void
{
    (new ZendMagicToArray())->toArray();
}
