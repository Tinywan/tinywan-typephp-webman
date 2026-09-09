<?php

function fixedReferenceStream(): void
{
    $value = fopen(__FILE__, 'r');
    $closure = static function () use (&$value): void {};
    $closure();
}
