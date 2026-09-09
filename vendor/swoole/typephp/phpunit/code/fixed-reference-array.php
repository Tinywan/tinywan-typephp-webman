<?php

function fixedReferenceArray(): void
{
    $value = [];
    $closure = static function () use (&$value): void {};
    $closure();
}
