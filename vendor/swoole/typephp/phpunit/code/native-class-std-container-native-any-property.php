<?php

#[Native]
class NativeAnyPropertyContainerValue
{
    public int $value;
}

#[Native]
class NativeContainerAnyPropertyHolder
{
    public any $value;
}

function native_container_native_any_property(): void
{
    $values = std::vector(NativeAnyPropertyContainerValue::class);
    $holder = new NativeContainerAnyPropertyHolder();
    $holder->value = $values;
}
