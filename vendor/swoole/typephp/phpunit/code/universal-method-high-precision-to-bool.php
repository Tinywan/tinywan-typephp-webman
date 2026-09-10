<?php

function main(): bool
{
    $integer = std::bigInt(0);
    $float = std::bigFloat('0');
    $integerFloat = std::bigFloat(1);
    $decimal = std::decimal('0');

    return $integer->toBool() || $float->toBool() || $integerFloat->toBool() || $decimal->toBool();
}
