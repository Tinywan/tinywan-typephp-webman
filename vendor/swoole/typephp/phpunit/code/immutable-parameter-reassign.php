<?php
function immutableParameterReassign(#[Immutable] string $value): string
{
    $value = 'changed';
    return $value;
}
