<?php

namespace TypePhp\Platform;

/**
 * Abstract platform base class.
 * Defines the interface every platform must implement.
 */
abstract class PlatformBase
{
    /**
     * Get the platform name.
     */
    abstract public function getName(): string;

    /**
     * Determine whether this is the current platform.
     */
    abstract public function isCurrent(): bool;

    /**
     * Get the compiler include-path flags.
     */
    abstract public function getIncludeFlags(array $includePaths): string;

    /**
     * Get the linker library-path flags.
     */
    abstract public function getLibraryPathFlags(array $libraryPaths): string;

    /**
     * Get the link-library flags.
     */
    abstract public function getLibraryFlags(array $libraries): string;

    /**
     * Get the object file extension.
     */
    abstract public function getObjectExtension(): string;

    /**
     * Get the executable file extension.
     */
    abstract public function getExecutableExtension(): string;

    /**
     * Get the shared library extension.
     */
    abstract public function getSharedLibraryExtension(): string;

    /**
     * Get the linker options required to produce a shared library.
     */
    abstract public function getSharedLinkFlag(): string;

    /**
     * Get the subsystem options for a console-less program; platforms where this does not apply return an empty string.
     */
    abstract public function getSubsystemOptions(bool $noConsole): string;

    /**
     * Get the platform C runtime library link configuration; platforms where this does not apply return an empty string.
     */
    abstract public function getCrtConfig(): string;

    /**
     * Get the path separator.
     */
    abstract public function getPathSeparator(): string;

    /**
     * Get the default C++ compiler command for this platform.
     */
    abstract public function getDefaultCompiler(): string;

    /**
     * Get the PHP installation directory.
     */
    abstract public function getPhpDir(): string;

    /**
     * Build the PHP include paths.
     */
    abstract public function buildPhpIncludePaths(string $phpDir): array;

    /**
     * Build the PHP library paths.
     */
    abstract public function buildPhpLibPaths(string $phpDir): array;

    /**
     * Detect the PHP library files.
     */
    abstract public function detectPhpLibs(string $phpDir): array;

    /**
     * Get the target file extension for the given build mode.
     */
    public function getTargetExtension(string $buildMode): string
    {
        return ($buildMode === 'ext' || $buildMode === 'lib')
            ? '.so'
            : $this->getExecutableExtension();
    }

    /**
     * Get the runtime library check warnings issued before building.
     */
    public function getBuildLibraryWarnings(
        string $phpDir,
        string $phpxDir,
        string $buildMode,
        bool $checkPhpxRuntime = true,
    ): array
    {
        if ($buildMode !== 'bin' && $buildMode !== 'lib') {
            return [];
        }

        $warnings = [];

        try {
            $this->detectPhpLibs($phpDir);
        } catch (\RuntimeException $e) {
            $ext = ltrim($this->getSharedLibraryExtension(), '.');
            $warnings[] = [
                'warning' => "The `libphp.{$ext}` is not found",
                'info' => $e->getMessage() . '. Run tpc.php in an interactive terminal to build it automatically, or set PHP_HOME',
            ];
        }

        $ext = ltrim($this->getSharedLibraryExtension(), '.');
        if (!is_file($phpxDir . '/lib/libphpx.' . $ext)) {
            $warnings[] = [
                'warning' => "The `libphpx.{$ext}` is not found",
                'info' => 'Run tpc.php in an interactive terminal to build it automatically, or set PHPX_HOME',
            ];
        }

        return $warnings;
    }

    /**
     * Whether this platform is suitable for parallel compilation using pcntl_fork.
     */
    public function supportsPcntlParallelCompile(): bool
    {
        return true;
    }

    public function getIntegerLiteralSuffix(): string
    {
        return 'LL';
    }

    /**
     * Normalize a path.
     */
    public function normalizePath(string $path): string
    {
        return str_replace('/', $this->getPathSeparator(), $path);
    }

    /**
     * Join path components.
     */
    public function joinPath(string ...$parts): string
    {
        return implode($this->getPathSeparator(), $parts);
    }

    public function removeCommonPrefix(string $short, string $long): string
    {
        $separator = $this->getPathSeparator();
        if ($separator === '\\') {
            $short = str_replace('/', '\\', $short);
            $long = str_replace('/', '\\', $long);
        }

        $short = rtrim($short, $separator);
        $long = rtrim($long, $separator);
        $shortParts = explode($separator, $short);
        $longParts = explode($separator, $long);
        $prefixLen = 0;
        $len = min(count($shortParts), count($longParts));

        for ($i = 0; $i < $len; $i++) {
            if ($shortParts[$i] === $longParts[$i]) {
                $prefixLen++;
            } else {
                break;
            }
        }

        return implode($separator, array_slice($longParts, $prefixLen));
    }

    /**
     * Get the default RPATH path list (only needed on macOS).
     *
     * @param string|null $phpxDir phpx directory path
     * @param string|null $phpDir PHP directory path
     * @return array RPATH path array
     */
    public function getDefaultRpaths(?string $phpxDir = null, ?string $phpDir = null): array
    {
        // Return an empty array by default; subclasses may override.
        return [];
    }
}
