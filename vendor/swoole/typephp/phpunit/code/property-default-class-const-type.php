<?php

class PropertyDefaultClassConstType
{
    public int $value = self::DEFAULT_VALUE;

    private const DEFAULT_VALUE = 'invalid';
}

function main(): void
{
}
