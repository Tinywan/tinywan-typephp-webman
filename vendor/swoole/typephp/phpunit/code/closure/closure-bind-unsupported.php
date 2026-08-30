<?php

function closureBindUnsupported(): void
{
    $callback = static function (): void {
    };
    Closure::bind($callback, null);
}
