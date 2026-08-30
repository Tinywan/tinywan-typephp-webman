<?php
use native_types;

function main(): void
{
    $bigfloat = std::bigFloat('2');
    echo $bigfloat ** 3;
}
