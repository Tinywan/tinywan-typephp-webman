<?php

#[Native]
class NativePhpPropertyContainerValue
{
    public int $value;
}

class NativeContainerPhpPropertyHolder
{
    public mixed $value;
}

function native_container_php_property(): void
{
    $values = std::vector(NativePhpPropertyContainerValue::class);
    $holder = new NativeContainerPhpPropertyHolder();
    $holder->value = $values;
}
