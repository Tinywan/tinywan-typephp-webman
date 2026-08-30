<?php

#[Native]
class NativeArrayKey {}

function main(): void
{
    $key = new NativeArrayKey();
    $values = [$key => 'value'];
}
