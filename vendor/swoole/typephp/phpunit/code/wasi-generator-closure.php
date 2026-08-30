<?php

function main(): void
{
    $generator = function (): iterable {
        yield 1;
    };
    var_dump($generator);
}
