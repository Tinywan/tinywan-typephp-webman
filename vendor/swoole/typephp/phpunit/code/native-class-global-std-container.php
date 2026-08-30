<?php

#[Native]
class NativeGlobalContainerValue
{
    public int $value;
}

function native_global_container(): void
{
    global $values;
    $values = std::vector(NativeGlobalContainerValue::class);
    $values[] = new NativeGlobalContainerValue();
}
