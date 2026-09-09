<?php

final class FixedReferenceObjectValue {}

function fixedReferenceObject(): void
{
    $value = new FixedReferenceObjectValue();
    $closure = static function () use (&$value): void {};
    $closure();
}
