<?php

#[Native]
class NativeReflectionMemberValue
{
    public int $value;

    public function read(): int
    {
        return $this->value;
    }
}

function main(): void
{
    new ReflectionMethod(NativeReflectionMemberValue::class, 'read');
}

