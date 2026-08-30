<?php

function main(): void
{
    $fn = function (&$value): void {
        $value = 1;
    };
}
