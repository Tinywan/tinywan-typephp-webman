<?php

#[Native]
class NativeStaticContainerValue
{
    public int $value;
}

function native_static_container(): void
{
    static $values = std::vector(NativeStaticContainerValue::class);
    $values[] = new NativeStaticContainerValue();
}
