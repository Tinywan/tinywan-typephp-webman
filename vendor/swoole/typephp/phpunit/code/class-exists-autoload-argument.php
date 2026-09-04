<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

class KnownClass
{
}

function autoloadFlag(): bool
{
    return false;
}

function main(): void
{
    var_dump(class_exists('KnownClass', autoloadFlag()));
}
