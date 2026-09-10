<?php

namespace TypePhp\Build;

use Composer\InstalledVersions;
use RuntimeException;

/** A Composer package that contributes C or C++ sources to a TypePHP native target. */
final readonly class ComposerNativePackage
{
    /**
     * @param list<string> $includeDirs
     * @param list<string> $sources
     */
    private function __construct(
        public string $name,
        public string $installPath,
        public string $kind,
        public int $abi,
        public int $cStandard,
        public int $cxxStandard,
        public array $includeDirs,
        public array $sources,
        public ?string $extensionName,
        public ?string $extensionModuleEntry,
    ) {
    }

    /** @return list<self> */
    public static function discover(): array
    {
        $packages = [];
        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $installPath = InstalledVersions::getInstallPath($package);
            if (!is_string($installPath)) {
                continue;
            }
            $manifestPath = $installPath . DIRECTORY_SEPARATOR . 'composer.json';
            $manifest = json_decode((string) @file_get_contents($manifestPath), true);
            if (!is_array($manifest)
                || !is_array($manifest['extra']['typephp-native'] ?? null)) {
                continue;
            }
            $packages[] = self::load($package);
        }

        usort(
            $packages,
            static function (self $left, self $right): int {
                $priority = ['runtime' => 0, 'bridge' => 1, 'library' => 2, 'extension' => 3];
                return [$priority[$left->kind], $left->name]
                    <=> [$priority[$right->kind], $right->name];
            },
        );
        return $packages;
    }

    public static function load(string $package): self
    {
        if ($package === 'swoole/php-ext-standard') {
            throw new RuntimeException(
                'The standard extension is built into swoole/php-nano; remove obsolete package `swoole/php-ext-standard`'
            );
        }
        $installPath = class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)
            ? InstalledVersions::getInstallPath($package)
            : null;
        $root = is_string($installPath) ? realpath($installPath) : false;
        if ($root === false) {
            $root = self::resolveSiblingPackage($package);
        }
        if ($root === null) {
            throw new RuntimeException(
                "Native dependency `{$package}` is not installed; run `composer require {$package}`"
            );
        }
        if (!is_dir($root)) {
            throw new RuntimeException("Unable to resolve Composer install path for `{$package}`");
        }

        $manifestPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $native = is_array($manifest) ? ($manifest['extra']['typephp-native'] ?? null) : null;
        if (!is_array($native)) {
            throw new RuntimeException(
                "Composer package `{$package}` does not publish extra.typephp-native"
            );
        }

        $kind = $native['kind'] ?? 'library';
        if (!is_string($kind) || !in_array($kind, ['runtime', 'bridge', 'extension', 'library'], true)) {
            throw new RuntimeException("Invalid native package kind in `{$package}`");
        }

        $extensionName = null;
        $extensionModuleEntry = null;
        if ($kind === 'extension') {
            if (!str_starts_with($package, 'swoole/php-ext-')) {
                throw new RuntimeException(
                    "Native extension package `{$package}` must use the `swoole/php-ext-*` naming convention"
                );
            }
            $extension = $native['extension'] ?? null;
            $extensionName = is_array($extension) ? ($extension['name'] ?? null) : null;
            $extensionModuleEntry = is_array($extension) ? ($extension['module-entry'] ?? null) : null;
            if (!is_string($extensionName)
                || preg_match('/^[a-z][a-z0-9_]*$/', $extensionName) !== 1
                || !is_string($extensionModuleEntry)
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $extensionModuleEntry) !== 1) {
                throw new RuntimeException("Invalid native extension metadata in `{$package}`");
            }
            $expectedPackage = 'swoole/php-ext-' . str_replace('_', '-', $extensionName);
            if ($package !== $expectedPackage) {
                throw new RuntimeException(
                    "Native extension `{$extensionName}` must use Composer package `{$expectedPackage}`"
                );
            }
            $runtimeConstraint = $manifest['require']['swoole/php-nano'] ?? null;
            if (!is_string($runtimeConstraint) || $runtimeConstraint === '') {
                throw new RuntimeException(
                    "Native extension package `{$package}` must require `swoole/php-nano`"
                );
            }
        }

        $abi = $native['abi'] ?? null;
        $cStandard = $native['c-standard'] ?? null;
        $cxxStandard = $native['cxx-standard'] ?? null;
        if (!is_int($abi) || !is_int($cStandard) || !is_int($cxxStandard)) {
            throw new RuntimeException("Invalid native ABI metadata in `{$package}`");
        }
        if ($cStandard !== 11) {
            throw new RuntimeException(
                "Native dependency `{$package}` requires unsupported C{$cStandard}; expected C11"
            );
        }
        if ($cxxStandard !== 17) {
            throw new RuntimeException(
                "Native dependency `{$package}` requires unsupported C++{$cxxStandard}; expected C++17"
            );
        }

        $sources = self::resolveEntries($root, $native['sources'] ?? null, false, $package);
        foreach ($sources as $source) {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            if (!in_array($extension, ['c', 'cc', 'cpp', 'cxx'], true)) {
                throw new RuntimeException(
                    "Native package `{$package}` contains unsupported source type: {$source}"
                );
            }
        }

        return new self(
            $package,
            $root,
            $kind,
            $abi,
            $cStandard,
            $cxxStandard,
            self::resolveEntries($root, $native['include-dirs'] ?? null, true, $package),
            $sources,
            $extensionName,
            $extensionModuleEntry,
        );
    }

    /**
     * Composer installs all three core packages as siblings below vendor/swoole.
     * The same layout is used by the monorepo checkout, where a package may not
     * yet be present in the checkout's generated InstalledVersions metadata.
     */
    private static function resolveSiblingPackage(string $package): ?string
    {
        if (!defined('TYPEPHP_ROOT_PATH') || !str_starts_with($package, 'swoole/')) {
            return null;
        }
        $candidate = realpath(
            dirname(TYPEPHP_ROOT_PATH) . DIRECTORY_SEPARATOR . substr($package, strlen('swoole/')),
        );
        if ($candidate === false || !is_dir($candidate)) {
            return null;
        }
        $manifest = json_decode((string) @file_get_contents($candidate . '/composer.json'), true);
        return is_array($manifest) && ($manifest['name'] ?? null) === $package
            ? $candidate
            : null;
    }

    /** @return list<string> */
    private static function resolveEntries(
        string $root,
        mixed $entries,
        bool $directories,
        string $package,
    ): array {
        if (!is_array($entries) || $entries === []) {
            throw new RuntimeException("Native source metadata is empty in `{$package}`");
        }

        $resolved = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '' || self::isAbsolutePath($entry)) {
                throw new RuntimeException("Invalid native source path in `{$package}`");
            }
            $path = realpath($root . DIRECTORY_SEPARATOR . $entry);
            $validType = $path !== false && ($directories ? is_dir($path) : is_file($path));
            if (!$validType || !self::isInside($root, (string) $path)) {
                throw new RuntimeException(
                    "Native source entry `{$entry}` is missing or escapes package `{$package}`"
                );
            }
            $resolved[] = $path;
        }
        return $resolved;
    }

    private static function isInside(string $root, string $path): bool
    {
        return $path === $root
            || str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return $path[0] === '/' || $path[0] === '\\'
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
