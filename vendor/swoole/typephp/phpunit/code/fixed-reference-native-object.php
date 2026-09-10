<?php

#[Native]
class FixedReferenceNativeObject
{
}

function fixedReferenceNativeObject(): void
{
    $value = new FixedReferenceNativeObject();
    $closure = static function () use (&$value): void {};
    $closure();
}
