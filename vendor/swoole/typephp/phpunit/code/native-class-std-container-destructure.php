<?php

#[Native]
class NativeDestructuredContainerValue
{
    public int $value;
}

function native_container_destructure(): void
{
    $values = std::array(NativeDestructuredContainerValue::class, 1);
    [$first] = $values;
}
