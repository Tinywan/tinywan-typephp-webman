<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final class SapiExtensionRequirements
{
    /**
     * @param list<string> ...$groups
     * @return list<string>
     */
    public static function merge(array ...$groups): array
    {
        $extensions = [];
        foreach ($groups as $group) {
            foreach ($group as $extension) {
                $name = self::normalize($extension);
                if ($name !== '') {
                    $extensions[$name] = true;
                }
            }
        }
        $result = array_keys($extensions);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @param list<string> $files @return list<string> */
    public static function fromEmbeddedVendorFiles(array $files): array
    {
        $manifests = [];
        foreach ($files as $file) {
            $vendor = self::vendorRoot($file);
            if ($vendor !== null) {
                $manifest = dirname($vendor) . DIRECTORY_SEPARATOR . 'composer.json';
                if (is_file($manifest)) {
                    $manifests[$manifest] = true;
                }
            }
        }
        $extensions = [];
        foreach (array_keys($manifests) as $manifestPath) {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($decoded)) {
                throw new \RuntimeException("Invalid Composer manifest: {$manifestPath}");
            }
            $require = $decoded['require'] ?? [];
            if (!is_array($require)) {
                throw new \RuntimeException("Composer `require` must be an object: {$manifestPath}");
            }
            foreach (array_keys($require) as $package) {
                if (is_string($package) && str_starts_with(strtolower($package), 'ext-')) {
                    $extensions[] = substr($package, 4);
                }
            }
        }
        return self::merge($extensions);
    }

    public static function normalize(string $extension): string
    {
        $extension = strtolower(trim($extension));
        if (str_starts_with($extension, 'ext-')) {
            $extension = substr($extension, 4);
        }
        $extension = str_replace(' ', '-', $extension);
        return match ($extension) {
            'zend-opcache', 'opcache' => 'opcache',
            default => str_replace('_', '-', $extension),
        };
    }

    private static function vendorRoot(string $file): ?string
    {
        $directory = is_dir($file) ? $file : dirname($file);
        while ($directory !== '' && $directory !== dirname($directory)) {
            if (basename($directory) === 'vendor' && is_file($directory . '/autoload.php')) {
                return $directory;
            }
            $directory = dirname($directory);
        }
        return null;
    }
}
