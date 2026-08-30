<?php
use native_types;

class NativePropertyStaticTypeMismatchBox
{
    public int $value = 0;
}

function native_property_static_type_mismatch(): void
{
    $box = new NativePropertyStaticTypeMismatchBox();
    $box->value = '123';
}
