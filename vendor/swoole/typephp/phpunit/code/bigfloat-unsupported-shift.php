<?php
declare(strict_types=1);
use native_types;
function main()
{
    $a = std::bigFloat("10.0");
    $b = 2;
    $c = $a << $b;
}
