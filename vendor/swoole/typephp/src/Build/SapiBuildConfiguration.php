<?php

namespace TypePhp\Build;

final class SapiBuildConfiguration
{
    /** @return list<string> */
    public static function parseTargets(string|array $value): array
    {
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim($value));
        if (!is_array($values)) {
            $values = [];
        }
        $targets = [];
        foreach ($values as $target) {
            if (!is_string($target) || trim($target) === '') {
                throw new \InvalidArgumentException('`sapi` entries must be non-empty strings');
            }
            $target = strtolower(trim($target));
            if (!in_array($target, ['embed', 'cli', 'fpm'], true)) {
                throw new \InvalidArgumentException(
                    "Invalid SAPI target `{$target}`. Expected embed, cli, or fpm.",
                );
            }
            $targets[] = $target;
        }
        $targets = array_values(array_unique($targets));
        if ($targets === []) {
            throw new \InvalidArgumentException('`sapi` must select embed, cli, or fpm');
        }
        return $targets;
    }
}
