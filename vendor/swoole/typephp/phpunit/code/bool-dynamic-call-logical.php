<?php

function isExecutableFile(string $path): bool
{
    return is_file($path) && is_executable($path);
}

function main(): void
{
    var_dump(isExecutableFile(__FILE__));
}
