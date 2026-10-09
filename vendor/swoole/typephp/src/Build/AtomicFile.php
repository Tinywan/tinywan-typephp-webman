<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final class AtomicFile
{
    public static function write(string $path, string $contents, string $temporaryPrefix = '.tmp-'): void
    {
        $directory = dirname($path);
        self::ensureDirectory($directory);
        $temporary = tempnam($directory, $temporaryPrefix);
        if ($temporary === false) {
            throw new \RuntimeException("Cannot create temporary file for: {$path}");
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new \RuntimeException("Cannot write temporary file for: {$path}");
            }
            self::replace($temporary, $path);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** Replace a target with a temporary file created in the same directory. */
    public static function replace(string $temporary, string $target): void
    {
        if (!is_file($temporary)) {
            throw new \RuntimeException("Replacement file does not exist: {$temporary}");
        }
        if (@rename($temporary, $target)) {
            return;
        }

        // Windows cannot rename over an existing file. A concurrent reader may
        // safely treat this short gap as a cache miss and rebuild the snapshot.
        if (PHP_OS_FAMILY === 'Windows' && is_file($target)) {
            if (!@unlink($target)) {
                throw new \RuntimeException("Cannot remove file before replacement: {$target}");
            }
            if (@rename($temporary, $target)) {
                return;
            }
        }

        throw new \RuntimeException("Cannot replace file: {$target}");
    }

    private static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create directory: {$directory}");
        }
    }
}
