<?php

#[Native]
class NativeAnyEscape
{
}

function main(): void
{
    $value = new NativeAnyEscape();
    $mixed = std::any($value);
}
