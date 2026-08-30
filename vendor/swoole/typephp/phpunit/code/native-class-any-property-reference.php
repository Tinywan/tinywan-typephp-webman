<?php

#[Native]
class NativeAnyPropertyReference
{
    public any $value = null;
}

function native_any_property_reference(): void
{
    $object = new NativeAnyPropertyReference();
    $reference =& $object->value;
    $reference = 42;
}
