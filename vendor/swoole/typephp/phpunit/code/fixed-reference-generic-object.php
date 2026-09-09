<?php

function fixedReferenceGenericObjectValue(): object
{
    return new stdClass();
}

function fixedReferenceGenericObject(): void
{
    $value = fixedReferenceGenericObjectValue();
    $closure = static function () use (&$value): void {};
    $closure();
}
