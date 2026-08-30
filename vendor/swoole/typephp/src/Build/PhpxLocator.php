<?php

namespace TypePhp\Build;

use Composer\InstalledVersions;
use RuntimeException;

final class PhpxLocator
{
    public static function resolve(string $rootPath): string
    {
        $phpxHome = getenv('PHPX_HOME');
        if (is_string($phpxHome) && $phpxHome !== '') {
            $resolved = self::existingDirectory($phpxHome);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('swoole/phpx')) {
            $installPath = InstalledVersions::getInstallPath('swoole/phpx');
            if (is_string($installPath)) {
                $resolved = self::existingDirectory($installPath);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        $resolved = self::existingDirectory(rtrim($rootPath, '/\\') . '/vendor/swoole/phpx');
        if ($resolved !== null) {
            return $resolved;
        }

        throw new RuntimeException(
            "phpx directory not found. Set PHPX_HOME or install swoole/phpx with Composer.",
        );
    }

    private static function existingDirectory(string $path): ?string
    {
        $path = rtrim($path, '/\\');
        if (!is_dir($path)) {
            return null;
        }
        return realpath($path) ?: $path;
    }
}
