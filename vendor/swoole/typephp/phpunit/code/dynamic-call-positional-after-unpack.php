<?php

function target(int $a, int $b): void
{
}

function main(): void
{
    $fn = 'target';
    $fn(...[1], 2);
}
