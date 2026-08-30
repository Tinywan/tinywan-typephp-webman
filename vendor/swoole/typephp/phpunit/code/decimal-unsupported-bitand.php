<?php
declare(strict_types=1);
use native_types;
function main()
{
    $a = std::decimal("3.14");
    $b = std::decimal("2.71");
    $c = $a & $b;
}
