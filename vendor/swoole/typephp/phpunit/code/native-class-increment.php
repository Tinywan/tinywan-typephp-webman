<?php

#[Native]
class NativeIncrementOperand {}

function main(): void
{
    $value = new NativeIncrementOperand();
    $value++;
}
