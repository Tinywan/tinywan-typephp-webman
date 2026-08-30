<?php

function immutableUnset(#[Immutable] object $value): void
{
    unset($value->name);
}
