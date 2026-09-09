<?php

function divideByZeroAny(): void
{
    $value = std::any(10);
    var_dump($value / 0);
}
