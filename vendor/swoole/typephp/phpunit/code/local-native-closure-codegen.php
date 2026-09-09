<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

function local_native_closure_codegen(): void
{
    $base = 1;
    $direct = static fn (int $value): int => $base + $value;
    var_dump($direct(2));

    $escaped = static fn (int $value): int => $value + 1;
    array_map($escaped, [1]);

    $dynamic = std::any(1);
    $dynamicRef = function () use (&$dynamic): void {
        $dynamic++;
    };
    $dynamicRef();
}
