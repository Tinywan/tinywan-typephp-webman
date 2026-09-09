<?php

function typedReferenceFloatArgument(float &$value): void {}

function typedReferenceArgumentTypeError(): void
{
    $value = 1;
    typedReferenceFloatArgument($value);
}
