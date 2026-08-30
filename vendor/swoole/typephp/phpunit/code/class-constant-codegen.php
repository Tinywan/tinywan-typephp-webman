<?php

class ClassConstantCodegen
{
    public const VALUE = 23;

    public function readThis(): int
    {
        return $this::VALUE;
    }
}

function readDynamicClassConstant(object $target): mixed
{
    return $target::VALUE;
}
