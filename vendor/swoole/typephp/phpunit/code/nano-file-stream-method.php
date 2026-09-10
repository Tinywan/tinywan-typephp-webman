<?php

function nanoFileStreamMethod(string $path): string
{
    $stream = fopen($path, 'rb')->toStream();
    return $stream->getContents();
}
