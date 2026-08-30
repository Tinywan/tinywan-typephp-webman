<?php

function immutableDestructuringWrite(#[Immutable] array $values): void
{
    [$values] = [[1, 2]];
}
