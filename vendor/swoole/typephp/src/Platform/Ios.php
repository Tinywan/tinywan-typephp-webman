<?php

namespace TypePhp\Platform;

/**
 * iPhoneOS cross-compilation target hosted on macOS.
 *
 * iOS executables link PHP and PHPX statically and must not inherit Homebrew
 * include paths, library paths, or runtime rpaths from the macOS host.
 */
class Ios extends UnixPlatform
{
    public static function supportsTarget(string $target): bool
    {
        return preg_match('/^(?:arm64|aarch64)-apple-ios(?:\d+(?:\.\d+)*)?$/i', $target) === 1;
    }

    public function getName(): string
    {
        return 'iOS';
    }

    public function isCurrent(): bool
    {
        return false;
    }

    public function getSharedLibraryExtension(): string
    {
        return '.a';
    }

    public function getDefaultCompiler(): string
    {
        return 'xcrun --sdk iphoneos clang++';
    }

    public function getDefaultRpaths(?string $phpxDir = null, ?string $phpDir = null): array
    {
        return [];
    }

    /**
     * An iPhoneOS SDK is a target sysroot, not a runnable PHP installation.
     * Its path is resolved from PHPX_HOME by CompilerBase, and php-config is
     * never executed for the target.
     */
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
            throw new \RuntimeException("The iPhoneOS libphp.a was not found: {$archive}");
        }

        return [
            'embed' => null,
            'static' => $archive,
            'is_shared' => false,
        ];
    }

    public function getBuildLibraryWarnings(
        string $phpDir,
        string $phpxDir,
        string $buildMode,
        bool $checkPhpxRuntime = true,
    ): array {
        if ($buildMode !== 'bin') {
            return [];
        }

        $warnings = [];
        try {
            $this->detectPhpLibs($phpDir);
        } catch (\RuntimeException $exception) {
            $warnings[] = [
                'warning' => 'The iPhoneOS `libphp.a` is not found',
                'info' => $exception->getMessage() . '. Rebuild the iPhoneOS SDK in the PHPX directory',
            ];
        }

        if (!is_file(rtrim($phpDir, '/') . '/lib/libphpx.a')) {
            $warnings[] = [
                'warning' => 'The iPhoneOS `libphpx.a` is not found',
                'info' => 'Build PHPX for iPhoneOS and install it into PHPX_HOME/ios/iphoneos-arm64',
            ];
        }

        return $warnings;
    }
}
