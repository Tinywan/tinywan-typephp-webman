<?php

#[Native]
class NativeArrayDimKey {}

function main(): void
{
    $key = new NativeArrayDimKey();
    $values = [];
    $values[$key] = 'value';
}
