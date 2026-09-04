<?php

namespace TypePhp\Backend;

use TypePhp\Platform\PlatformBase;

/**
 * Abstract base class for compiler backends.
 * Defines the interface that all compilers must implement.
 */
abstract class CompilerBackend
{
    /**
     * The platform instance.
     */
    protected PlatformBase $platform;

    /**
     * Path of the most recently created Response File, used for cleanup after the build completes.
     */
    protected string $lastResponseFile = '';

    public function __construct(PlatformBase $platform)
    {
        $this->platform = $platform;
    }

    /**
     * Get the compiler name.
     */
    abstract public function getName(): string;

    /**
     * Get the compiler command.
     */
    abstract public function getCompilerCommand(): string;

    /**
     * Get the linker command.
     */
    abstract public function getLinkerCommand(): string;

    public function supportsPrecompiledHeaders(): bool
    {
        return false;
    }

    public function getPrecompiledHeaderArtifact(string $headerFile): string
    {
        throw new \LogicException($this->getName() . ' does not support precompiled headers');
    }

    /**
     * Build the complete compile command.
     */
    abstract public function buildCompileCommand(
        string $sourceFile,
        string $outputFile,
        array $options = []
    ): string;

    /**
     * Build the compile command for C files (excludes C++-specific options).
     */
    abstract public function buildCCompileCommand(
        string $sourceFile,
        string $outputFile,
        array $options = []
    ): string;

    /**
     * Build the compile command for native source files (assembly/Objective-C, etc., using -x to specify the language).
     *
     * @param string $language GCC/Clang language identifier (assembler, objective-c, objective-c++, etc.)
     */
    abstract public function buildNativeCompileCommand(
        string $sourceFile,
        string $outputFile,
        array $options = [],
        string $language = ''
    ): string;

    /**
     * Build the complete link command.
     */
    abstract public function buildLinkCommand(
        array $objectFiles,
        string $outputFile,
        array $options = []
    ): string;

    /**
     * Build compile options (excludes file paths).
     *
     * @param array $config Compile configuration
     *   - optimize: optimization level (0-3)
     *   - debug_info: whether to generate debug information
     *   - sanitize: sanitizer type (address, undefined, etc.)
     *   - cpp_std: C++ standard version
     *   - is_zts: whether ZTS mode is enabled
     *   - build_mode: build mode ('bin' or 'ext')
     *   - enable_profiler: whether to enable profiling
     *   - suppressed_warnings: array of warning codes to suppress
     *   - cxxflags: user-defined compile flags
     *   - compiler_pdb: MSVC compiler PDB output path
     */
    abstract public function buildCompileOptions(array $config = []): string;

    /**
     * Build link options (excludes file paths).
     *
     * @param array $config Link configuration
     *   - debug_info: whether to generate debug information
     *   - no_console: whether to hide the console window
     *   - build_mode: build mode ('bin' or 'ext')
     *   - sanitize: sanitizer type
     */
    abstract public function buildLinkOptions(array $config = []): string;

    /**
     * Get the platform instance.
     */
    public function getPlatform(): PlatformBase
    {
        return $this->platform;
    }

    /**
     * Format include paths.
     */
    protected function formatIncludePaths(array $includePaths): string
    {
        return $this->platform->getIncludeFlags($includePaths);
    }

    /**
     * Format library paths.
     */
    protected function formatLibraryPaths(array $libraryPaths): string
    {
        return $this->platform->getLibraryPathFlags($libraryPaths);
    }

    /**
     * Format library files.
     */
    protected function formatLibraries(array $libraries): string
    {
        return $this->platform->getLibraryFlags($libraries);
    }

    protected function formatDefineFlag(string $define, string $prefix): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(=(?:[A-Za-z0-9_.,:+\/@%-]+))?$/', $define) === 1) {
            return $prefix . $define;
        }

        return $prefix . escapeshellarg($define);
    }

    /**
     * Write the object file list to a Response File to avoid exceeding the OS command-line length limit (8191 characters on Windows).
     *
     * @param array  $objectFiles List of object file paths.
     * @param string $targetFile  Final output file path (the Response File is written to the same directory).
     * @return string Linker argument, e.g. @build/project.rsp
     */
    protected function createResponseFile(array $objectFiles, string $targetFile): string
    {
        $rspFile = dirname($targetFile) . DIRECTORY_SEPARATOR . basename($targetFile) . '.rsp';
        $this->lastResponseFile = $rspFile;
        $lines = [];
        foreach ($objectFiles as $file) {
            // Wrap paths containing spaces in double quotes; supported by both MSVC link.exe and GCC/Clang.
            if (str_contains($file, ' ')) {
                $file = '"' . $file . '"';
            }
            $lines[] = $file;
        }
        file_put_contents($rspFile, implode("\n", $lines));
        return escapeshellarg('@' . $rspFile);
    }

    /**
     * Delete the most recently created Response File temporary file.
     */
    public function cleanupResponseFile(): void
    {
        if ($this->lastResponseFile !== '' && file_exists($this->lastResponseFile)) {
            unlink($this->lastResponseFile);
        }
    }
}
