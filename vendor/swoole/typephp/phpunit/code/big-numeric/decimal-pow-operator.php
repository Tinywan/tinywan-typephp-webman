<?php
use native_types;

function main(): void
{
    $decimal = std::decimal('2');
    echo $decimal ** 3;
}
