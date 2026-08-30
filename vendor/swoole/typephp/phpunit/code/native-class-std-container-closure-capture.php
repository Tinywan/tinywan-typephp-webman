<?php

#[Native]
class NativeClosureContainerValue
{
    public int $value;
}

function native_container_closure(): Closure
{
    $values = std::vector(NativeClosureContainerValue::class);
    return static function () use ($values): int {
        return count($values);
    };
}
