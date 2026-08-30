<?php

class TypedObjectUnsetNullValue
{
}

function main(): void
{
    $value = new TypedObjectUnsetNullValue();
    $value = null;
    $value = new TypedObjectUnsetNullValue();
    unset($value);
    $value = null;
    $value = new TypedObjectUnsetNullValue();
}
