<?php

function fixedReferenceStatic(): void
{
    static $value = 'fixed';
    $closure = static function () use (&$value): void {};
    $closure();
}
