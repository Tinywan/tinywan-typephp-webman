<?php

function immutableArrayMutatingMethod(#[Immutable] array $values): void
{
    $values->sort();
}
