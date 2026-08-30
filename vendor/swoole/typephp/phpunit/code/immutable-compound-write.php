<?php

function immutableCompoundWrite(#[Immutable] array $values): void
{
    $values[0] += 1;
}
