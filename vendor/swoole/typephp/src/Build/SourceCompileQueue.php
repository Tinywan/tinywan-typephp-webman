<?php

namespace TypePhp\Build;

final class SourceCompileQueue
{
    /** @param list<string> $sources @return list<string> */
    public static function largestFirst(array $sources): array
    {
        $entries = [];
        foreach (array_values($sources) as $index => $source) {
            $size = @filesize($source);
            $entries[] = ['source' => $source, 'size' => $size === false ? -1 : $size, 'index' => $index];
        }

        usort($entries, static function (array $left, array $right): int {
            return ($right['size'] <=> $left['size']) ?: ($left['index'] <=> $right['index']);
        });

        return array_column($entries, 'source');
    }
}
