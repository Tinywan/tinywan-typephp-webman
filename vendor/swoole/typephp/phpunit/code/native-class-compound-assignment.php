<?php

#[Native]
class NativeCompoundOperand {}

function main(): void
{
    $value = new NativeCompoundOperand();
    $value += 1;
}
