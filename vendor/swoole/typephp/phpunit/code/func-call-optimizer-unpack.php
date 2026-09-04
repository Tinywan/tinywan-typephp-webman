<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

function main(): void
{
    $intvalArgs = ['ff', 16];
    $roundArgs = [2.5, 0, PHP_ROUND_HALF_DOWN];
    $nullArgs = [null];
    $arrayKeysArgs = [['a' => 1]];
    $functionExistsArgs = ['strlen'];

    var_dump(intval(...$intvalArgs));
    var_dump(round(...$roundArgs));
    var_dump(is_null(...$nullArgs));
    var_dump(array_keys(...$arrayKeysArgs));
    var_dump(function_exists(...$functionExistsArgs));
}
