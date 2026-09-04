<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

function main(): void
{
    $args = [[1, 2, 3]];
    var_dump(count(...$args));
    var_dump(count(...[[1, 2, 3]]));

    try {
        var_dump(count(...[]));
        echo "argument-count-error-not-thrown\n";
    } catch (ArgumentCountError $error) {
        echo "caught=", $error->getMessage(), "\n";
    }
}
