<?php

function closureCallTypedUnsupported(Closure $callback, object $target): void
{
    $callback->call($target);
}
