<?php

function nativeScalarReferenceCapture(): void
{
    $changed = false;
    $set = static function () use (&$changed): void {
        $changed = true;
    };
    $set();
}
