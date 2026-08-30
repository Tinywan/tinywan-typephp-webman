<?php

function bool_literal_identical(bool $pjax): array
{
    return [
        true === $pjax,
        $pjax === true,
        false !== $pjax,
        $pjax !== false,
    ];
}

function dynamic_bool_literal_identical(mixed $value): bool
{
    return true === $value;
}
