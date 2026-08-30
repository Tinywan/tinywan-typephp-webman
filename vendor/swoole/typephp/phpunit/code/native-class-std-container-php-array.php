<?php

#[Native]
class NativePhpArrayContainerValue
{
    public int $value;
}

function native_container_php_array(): void
{
    $values = std::vector(NativePhpArrayContainerValue::class);
    $array = [];
    $array[] = $values;
}
