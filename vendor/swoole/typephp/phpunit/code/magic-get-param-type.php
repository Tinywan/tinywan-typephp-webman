<?php

class MagicGetParamTypeInvalid
{
    public function __get(int $name): mixed
    {
        return null;
    }
}

function main(): void
{
}
