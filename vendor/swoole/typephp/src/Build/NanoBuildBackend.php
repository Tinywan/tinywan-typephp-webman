<?php

namespace TypePhp\Build;

/** Selects the runtime build backend used by the --nano policy mode. */
final class NanoBuildBackend
{
    /** Existing Windows host build linked through the PHP/PHPX import libraries. */
    public const WINDOWS_DLL = 'windows-dll';

    /** Composer package manifests whose C/C++ sources are compiled into the program. */
    public const COMPOSER_SOURCES = 'composer-sources';

    public static function forHost(string $osFamily): string
    {
        return $osFamily === 'Windows' ? self::WINDOWS_DLL : self::COMPOSER_SOURCES;
    }

    public static function composesRuntimeSources(string $osFamily): bool
    {
        return self::forHost($osFamily) === self::COMPOSER_SOURCES;
    }
}
