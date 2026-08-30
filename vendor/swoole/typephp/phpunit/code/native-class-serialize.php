<?php

#[Native]
class NativeSerializeBoundary {}

function native_serialize_boundary(): void
{
    $value = new NativeSerializeBoundary();
    serialize($value);
}
