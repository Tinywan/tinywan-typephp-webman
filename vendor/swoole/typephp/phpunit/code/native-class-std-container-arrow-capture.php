<?php

#[Native]
class NativeArrowContainerValue
{
    public int $value;
}

function native_container_arrow(): Closure
{
    $values = std::vector(NativeArrowContainerValue::class);
    return static fn(): int => count($values);
}
