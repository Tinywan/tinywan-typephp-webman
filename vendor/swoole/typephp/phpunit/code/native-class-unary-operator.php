<?php

#[Native]
class NativeUnaryOperand {}

function main(): void
{
    $value = new NativeUnaryOperand();
    $result = -$value;
}
