<?php

function nullableValue(#[NotNull] ?string $value): ?string
{
    return $value;
}

function unionNullableValue(#[NotNull] string|null $value): string|null
{
    return $value;
}

function main(): void
{
    $nullableValue = function (#[NotNull] ?string $value): ?string {
        return $value;
    };
    echo $nullableValue('typephp');
}
