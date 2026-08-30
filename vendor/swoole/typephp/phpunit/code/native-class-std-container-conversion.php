<?php

#[Native]
class NativeConvertedContainerValue
{
    public int $value;
}

function native_container_conversion(): array
{
    $values = std::vector(NativeConvertedContainerValue::class);
    return $values->toArray();
}
