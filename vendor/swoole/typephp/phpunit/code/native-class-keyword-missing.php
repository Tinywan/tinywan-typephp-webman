<?php

#[Native]
class MissingNativeConversion
{
    public int $value;
}

function main(): void
{
    $value = new MissingNativeConversion();
    $value->toInt();
}
