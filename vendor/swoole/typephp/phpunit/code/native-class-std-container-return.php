<?php

#[Native]
class NativeReturnedContainerValue
{
    public int $value;
}

function native_container_return(): array
{
    $values = std::vector(NativeReturnedContainerValue::class);
    $values[] = new NativeReturnedContainerValue();
    return $values;
}
