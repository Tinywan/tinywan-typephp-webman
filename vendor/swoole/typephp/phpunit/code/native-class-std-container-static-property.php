<?php

#[Native]
class NativeStaticPropertyContainerValue
{
    public int $value;
}

class NativeContainerStaticPropertyHolder
{
    public static mixed $value;
}

function native_container_static_property(): void
{
    $values = std::vector(NativeStaticPropertyContainerValue::class);
    NativeContainerStaticPropertyHolder::$value = $values;
}
