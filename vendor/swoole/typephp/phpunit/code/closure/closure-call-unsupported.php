<?php

function closureCallUnsupported(): void
{
    $callback = static fn(): string => 'value';
    echo $callback->call(new stdClass());
}
