<?php

function anyReferenceCapture(): void
{
    $changed = std::any(false);
    $set = static function () use (&$changed): void {
        $changed = true;
    };
    $set();
}
