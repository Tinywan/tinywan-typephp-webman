<?php

#[Native]
class NativeArrayAccessIsset {}

function main(): void
{
    $value = new NativeArrayAccessIsset();
    var_dump(isset($value[0]));
}
