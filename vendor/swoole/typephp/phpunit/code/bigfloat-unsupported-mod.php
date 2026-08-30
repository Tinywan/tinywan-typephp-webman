<?php
declare(strict_types=1);
use native_types;
function main()
{
    $a = std::bigFloat("3.14");
    $b = std::bigFloat("2.71");
    $c = $a % $b;
}
