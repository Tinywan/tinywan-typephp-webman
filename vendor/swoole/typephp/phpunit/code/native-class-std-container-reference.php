<?php

#[Native]
class NativeReferencedContainerValue
{
    public int $value;
}

function native_container_reference(): void
{
    $values = std::vector(NativeReferencedContainerValue::class);
    $alias =& $values;
}
