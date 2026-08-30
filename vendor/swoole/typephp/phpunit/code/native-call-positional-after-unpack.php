<?php

function target(int $a, int $b): void
{
}

function main(): void
{
    target(...[1], 2);
}
