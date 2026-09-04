<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

function main(): void
{
    $base = 16;

    var_dump(intval('ff', 16));
    var_dump(intval('ff', $base));
}
