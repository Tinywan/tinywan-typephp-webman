<?php

function fixedReferenceToRefTarget(mixed &$value): void {}

function fixedReferenceToRef(): void
{
    $value = [];
    fixedReferenceToRefTarget($value->toRef());
}
