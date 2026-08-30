<?php

class Target
{
    public function __construct(int $a, int $b)
    {
    }
}

function main(): void
{
    new Target(...[1], 2);
}
