<?php
function main(): void
{
    $n = std::int(9);
    $n /= 0;
    var_dump($n);
}
