<?php

#[Native]
class NativeArithmeticValue
{
    public int $value = 1;
}

function main(): void
{
    $value = new NativeArithmeticValue();
    var_dump($value + 1);
}
