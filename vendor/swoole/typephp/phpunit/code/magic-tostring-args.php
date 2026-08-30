<?php

class MagicToStringArgsInvalid
{
    public function __toString($extra): string
    {
        return '';
    }
}

function main(): void
{
}
