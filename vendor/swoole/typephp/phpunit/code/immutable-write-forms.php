<?php

function immutableWriteForms(#[Immutable] array $values): void
{
    foreach ($values as &$value) {
        $value++;
    }
}
