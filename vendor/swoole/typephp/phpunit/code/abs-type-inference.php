<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace {
    use function abs as absolute;

    function inferredAbsInt(int $value): void
    {
        $result = abs($value);
        var_dump($result);
    }

    function inferredAliasedAbsInt(int $value): void
    {
        $result = absolute($value);
        var_dump($result);
    }

    function inferredLocalAbsInt(): void
    {
        $value = PHP_INT_MIN;
        $result = abs($value);
        var_dump($result);
    }

    function inferredAbsFloat(float $value): void
    {
        $result = abs($value);
        var_dump($result);
    }

    function inferredAbsDynamic(mixed $value): void
    {
        $result = abs($value);
        var_dump($result);
    }
}

namespace AbsInferenceNamespace {
    function inferredNamespacedAbs(int $value): void
    {
        // A runtime-provided namespaced function may shadow global abs().
        $result = abs($value);
        var_dump($result);
    }
}

namespace {
    function main(): void
    {
    }
}
