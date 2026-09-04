<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

function main(): void
{
    $withBase = ['ff', 16];
    $single = ['42'];

    var_dump(intval(...$withBase));
    var_dump(intval(...$single));
    var_dump(strval(...$single));
    var_dump(floatval(...$single));
    var_dump(boolval(...$single));
    var_dump(intval('ff', ...[16]));
    var_dump(intval(value: '42'));
}
