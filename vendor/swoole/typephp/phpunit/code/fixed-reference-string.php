<?php

function fixedReferenceString(): void
{
    $value = 'fixed';
    $closure = static function () use (&$value): void {};
    $closure();
}
