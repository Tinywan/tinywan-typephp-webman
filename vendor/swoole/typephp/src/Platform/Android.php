<?php

namespace TypePhp\Platform;

/** Android arm64-v8a cross-compilation target. */
final class Android extends UnixPlatform
{
    private const DEFAULT_API = 24;

    public static function supportsTarget(string $target): bool
    {
        return preg_match('/^aarch64-linux-android(?:[0-9]+)?$/i', $target) === 1;
    }

    public static function getApiLevel(string $target = ''): int
    {
        if (preg_match('/^aarch64-linux-android([0-9]+)$/i', $target, $matches) === 1) {
            return (int) $matches[1];
        }

        $configured = getenv('TYPEPHP_ANDROID_API');
        return is_string($configured) && preg_match('/^[0-9]+$/', $configured) === 1
            ? (int) $configured
            : self::DEFAULT_API;
    }

    public function getName(): string
    {
        return 'Android arm64-v8a';
    }

    public function isCurrent(): bool
    {
        return false;
    }

    public function getSharedLibraryExtension(): string
    {
        return '.so';
    }

    public function getSharedLinkFlag(): string
    {
        return '-shared -static-libstdc++'
            . ' -Wl,-z,max-page-size=16384,-z,common-page-size=16384'
            . ' -Wl,-z,relro,-z,now';
    }

    public function getDefaultCompiler(): string
    {
        $ndkRoot = getenv('ANDROID_NDK_HOME') ?: getenv('ANDROID_NDK_ROOT');
        if (!is_string($ndkRoot) || $ndkRoot === '') {
            throw new \RuntimeException('Set ANDROID_NDK_HOME to an Android NDK installation');
        }

        $hostTag = match (PHP_OS_FAMILY) {
            'Darwin' => 'darwin-x86_64',
            'Windows' => 'windows-x86_64',
            default => 'linux-x86_64',
        };
        $compiler = rtrim($ndkRoot, '/\\')
            . '/toolchains/llvm/prebuilt/' . $hostTag . '/bin/clang++';
        if (!is_file($compiler) || !is_executable($compiler)) {
            throw new \RuntimeException("Android NDK clang++ was not found: {$compiler}");
        }
        return $compiler;
    }

    public function getDefaultRpaths(?string $phpxDir = null, ?string $phpDir = null): array
    {
        return [];
    }

    public function buildPhpIncludePaths(string $phpDir): array
    {
        $paths = [
            $phpDir . '/include/php',
            $phpDir . '/include/php/main',
            $phpDir . '/include/php/TSRM',
            $phpDir . '/include/php/Zend',
            $phpDir . '/include/php/ext',
            $phpDir . '/include/php/ext/date/lib',
        ];
        return array_values(array_filter($paths, is_dir(...)));
    }

    public function buildPhpLibPaths(string $phpDir): array
    {
        $libDir = rtrim($phpDir, '/') . '/lib';
        return is_dir($libDir) ? [$libDir] : [];
    }

    public function detectPhpLibs(string $phpDir): array
    {
        $archive = rtrim($phpDir, '/') . '/lib/libphp.a';
        if (!is_file($archive)) {
            throw new \RuntimeException("The Android libphp.a was not found: {$archive}");
        }
        return ['embed' => null, 'static' => $archive, 'is_shared' => false];
    }

    /** Keep the mutually dependent PHP/PHPX archives in one linker group. */
    public function getLibraryFlags(array $libraries): string
    {
        $archives = [];
        $systemLibraries = [];
        foreach ($libraries as $library) {
            if (is_string($library) && str_ends_with($library, '.a') && is_file($library)) {
                $archives[] = escapeshellarg($library);
            } else {
                $name = basename((string) $library);
                if (str_starts_with($name, 'lib')) {
                    $name = substr($name, 3);
                }
                $systemLibraries[] = '-l' . preg_replace('/\.(?:a|so)$/', '', $name);
            }
        }

        $flags = [];
        if ($archives !== []) {
            $flags[] = '-Wl,--start-group ' . implode(' ', $archives) . ' -Wl,--end-group';
        }
        return implode(' ', array_merge($flags, $systemLibraries));
    }

    public function getBuildLibraryWarnings(
        string $phpDir,
        string $phpxDir,
        string $buildMode,
        bool $checkPhpxRuntime = true,
    ): array {
        return [];
    }
}
