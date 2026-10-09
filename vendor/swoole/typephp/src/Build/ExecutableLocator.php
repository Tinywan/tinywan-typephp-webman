<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final class ExecutableLocator
{
    public static function resolve(string $program): ?string
    {
        if ($program === '') {
            return null;
        }

        if (self::isPath($program)) {
            return self::resolveCandidate($program);
        }

        $path = getenv('PATH');
        if ($path === false || $path === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory === '') {
                continue;
            }
            foreach (self::executableExtensions($program) as $extension) {
                $candidate = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $program . $extension;
                $resolved = self::resolveCandidate($candidate);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    private static function resolveCandidate(string $path): ?string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_file($resolved)) {
            return null;
        }

        if (!is_executable($resolved)) {
            return null;
        }

        return $resolved;
    }

    private static function isPath(string $program): bool
    {
        return str_contains($program, '/')
            || str_contains($program, '\\')
            || preg_match('/^[A-Za-z]:/', $program) === 1;
    }

    /**
     * @return list<string>
     */
    private static function executableExtensions(string $program): array
    {
        if (DIRECTORY_SEPARATOR !== '\\' || pathinfo($program, PATHINFO_EXTENSION) !== '') {
            return [''];
        }

        $pathExtensions = getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD';
        return array_values(array_filter(explode(';', $pathExtensions), static fn (string $extension): bool => $extension !== ''));
    }
}
