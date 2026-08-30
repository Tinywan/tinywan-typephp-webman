<?php

#[Native]
class NativeMixedPropertyReference
{
    public mixed $value = null;
}

function native_mixed_property_reference(): void
{
    $object = new NativeMixedPropertyReference();
    $reference =& $object->value;
}
