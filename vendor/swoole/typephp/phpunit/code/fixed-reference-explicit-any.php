<?php

function fixedReferenceExplicitAny(): void
{
    $value = std::any('fixed');
    $closure = static function () use (&$value): void {
        $value = [];
    };
    $closure();
    $array = $value->toArray();
}
