<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

function main(): void
{
    echo count([1, 2, 3]), "\n";
    echo count([[1, 2], [3]]), "\n";
    echo count([1.5, 'text', true, false, null]), "\n";
    echo count([-2, +3, -1.5]), "\n";
    echo count([]), "\n";
}
