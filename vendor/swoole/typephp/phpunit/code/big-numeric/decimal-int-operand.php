<?php

function multiplyDecimalByInt(int $factor): string
{
    $value = std::decimal('123.456');
    $value = $value * 1000;
    $value = $value * $factor;
    return $value->toString();
}

function multiplyDecimalByVar($factor): string
{
    $value = std::decimal('123.456');
    $value = $value * $factor;
    return $value->toString();
}

function main(): void
{
    echo multiplyDecimalByInt(1000), "\n";
    echo multiplyDecimalByVar(1000), "\n";
}
