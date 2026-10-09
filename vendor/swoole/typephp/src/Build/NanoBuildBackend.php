<?php

namespace TypePhp\Build;

/** Selects the source-composed runtime backend used by a --nano application. */
final class NanoBuildBackend
{
    /** Composer package manifests whose C/C++ sources are compiled into the program. */
    public const COMPOSER_SOURCES = 'composer-sources';

    public static function forHost(string $platformName): string
    {
        return self::COMPOSER_SOURCES;
    }

    public static function composesRuntimeSources(string $platformName): bool
    {
        return true;
    }
}
