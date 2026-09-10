<?php

function referenceCaptureAutoDegrade(): Closure
{
    $value = 'fixed';
    return static function () use (&$value): string {
        $value = 'changed';
        return $value;
    };
}

function referenceCaptureParameterDegrade(string $value): Closure
{
    return static function () use (&$value): array {
        $value = ['changed'];
        return $value;
    };
}
