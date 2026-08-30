<?php
use native_types;

class NativePropertyWriteConversionBox
{
    public int $value = 0;
    public string $name = '';
    public array $items = [];
}

function native_property_write_conversion(
    NativePropertyWriteConversionBox $box,
    int $nativeValue,
    $dynamicValue,
    $dynamicName,
    $dynamicItems,
): void
{
    $box->value = $nativeValue;
    $box->value = $dynamicValue;
    $box->name = $dynamicName;
    $box->items = $dynamicItems;
}
