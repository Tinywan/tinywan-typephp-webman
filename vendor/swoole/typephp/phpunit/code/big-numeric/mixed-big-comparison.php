<?php
use native_types;

function main(): void
{
    $bigint = std::bigInt('2');
    $decimal = std::decimal('2');
    var_dump($bigint == $decimal);
}
