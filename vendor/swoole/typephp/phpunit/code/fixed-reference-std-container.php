<?php

function fixedReferenceStdContainer(): void
{
    $value = std::vector(Type::Int);
    $closure = static function () use (&$value): void {};
    $closure();
}
