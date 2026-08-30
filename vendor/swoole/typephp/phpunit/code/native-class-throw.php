<?php

#[Native]
class NativeThrownValue
{
}

function main(): void
{
    $value = new NativeThrownValue();
    throw $value;
}

