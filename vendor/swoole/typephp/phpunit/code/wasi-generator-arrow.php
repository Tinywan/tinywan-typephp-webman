<?php

function main(): void
{
    $generator = fn (): iterable => yield 1;
    var_dump($generator);
}
