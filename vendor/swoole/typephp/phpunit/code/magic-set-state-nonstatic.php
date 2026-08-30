<?php

class MagicSetStateNonStaticInvalid
{
    public function __set_state(array $properties): object
    {
        return new self();
    }
}

function main(): void
{
}
