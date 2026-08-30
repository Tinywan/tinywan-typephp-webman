<?php

#[Native]
class NativeArrayAccessUnset {}

function main(): void
{
    $value = new NativeArrayAccessUnset();
    unset($value[0]);
}
