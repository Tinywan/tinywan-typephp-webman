<?php

#[Native]
class NativeVariableProperty
{
    public int $property = 1;
}

function main(): void
{
    $value = new NativeVariableProperty();
    $property = 'property';
    var_dump($value->$property);
}
