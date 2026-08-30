<?php
declare(strict_types=1);
use native_types;

function main(): void
{
    $integer = std::bigInt("123456789012345678901234567890");
    echo ($integer * 9)->toString(), "\n";

    $float = std::bigFloat("1000000000000000000000000000000");
    echo ($float + std::bigFloat("1"))->toString(), "\n";

    $decimal = std::decimal("12345.00000000000000001");
    echo ($decimal + std::decimal("3.14159265358979323"))->toString(), "\n";
}
