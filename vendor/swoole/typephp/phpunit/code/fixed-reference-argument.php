<?php

function fixedReferenceArgumentTarget(string &$value): void {}

function fixedReferenceArgument(): void
{
    $value = 'fixed';
    fixedReferenceArgumentTarget($value);
}
