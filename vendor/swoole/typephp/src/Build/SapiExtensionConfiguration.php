<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final class SapiExtensionConfiguration
{
    /** Extensions compiled unconditionally by php-src. */
    private const array CORE_EXTENSIONS = [
        'core', 'date', 'hash', 'json', 'pcre', 'random', 'reflection', 'spl', 'standard',
    ];

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    public static function configureOptions(string $phpSourceDirectory, array $extensions): array
    {
        $options = [];
        foreach (SapiExtensionRequirements::merge($extensions) as $extension) {
            if (in_array($extension, self::CORE_EXTENSIONS, true)) {
                continue;
            }
            if ($extension === 'opcache') {
                $options[] = '--enable-opcache';
                continue;
            }
            $directory = $phpSourceDirectory . '/ext/' . str_replace('-', '_', $extension);
            if (!is_dir($directory)) {
                throw new \RuntimeException("PHP extension `{$extension}` is not bundled with php-src and cannot be linked into the SAPI runtime");
            }
            $option = self::findConfigureOption($directory, $extension);
            // An extension directory without a configure switch is part of the
            // core build and needs no extra argument.
            if ($option !== null) {
                $options[] = $option;
            }
        }
        return array_values(array_unique($options));
    }

    private static function findConfigureOption(string $directory, string $extension): ?string
    {
        foreach ([$directory . '/config0.m4', $directory . '/config.m4'] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $contents = (string) file_get_contents($file);
            if (preg_match_all(
                '/PHP_ARG_(WITH|ENABLE)\s*\(\s*\[?([A-Za-z0-9_-]+)\]?/i',
                $contents,
                $matches,
                PREG_SET_ORDER,
            ) === false) {
                continue;
            }
            foreach ($matches as $match) {
                if (SapiExtensionRequirements::normalize($match[2]) !== $extension) {
                    continue;
                }
                return ($match[1] === 'WITH' ? '--with-' : '--enable-') . $match[2];
            }
        }
        return null;
    }
}
