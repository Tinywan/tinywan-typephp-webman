<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

class Real
{
}

enum Suit
{
    case Hearts;
}

function main(): void
{
    var_dump(class_exists('Real'));
    var_dump(class_exists('Suit'));
}
