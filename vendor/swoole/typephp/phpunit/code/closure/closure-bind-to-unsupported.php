<?php

function closureBindToUnsupported(object $target): void
{
    $callback = static function (): void {
    };
    $callback->bindTo($target);
}
