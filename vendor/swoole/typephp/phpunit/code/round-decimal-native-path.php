<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

function main(): void
{
    $decimal = std::decimal('2.567');
    $whole = round($decimal);
    $precise = round($decimal, 2);
}
