<?php

namespace TypePhp\Build;

use TypePhp\Backend\CompilerBackend;

final readonly class PrecompiledHeaderManager
{
    private const int MAX_CACHE_ENTRIES = 8;
    private const int MAX_CACHE_AGE = 30 * 86400;

    public function __construct(
        private CompilerBackend $backend,
        private NativeBuilder $builder,
    ) {
    }

    /**
     * @param list<string> $headers
     * @param list<string> $dependencyDirectories
     * @return array{header: string, artifact: string, cached: bool, command: string}
     */
    public function prepare(
        array $headers,
        array $dependencyDirectories,
        string $cacheDirectory,
        CompileOptions $options,
    ): array {
        if (!$this->backend->supportsPrecompiledHeaders()) {
            throw new \LogicException($this->backend->getName() . ' does not support precompiled headers');
        }

        $fingerprint = $this->buildFingerprint($headers, $dependencyDirectories, $options);
        $directory = rtrim($cacheDirectory, '/\\') . DIRECTORY_SEPARATOR . $fingerprint;
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create precompiled header cache directory: ' . $directory);
        }

        $headerFile = $directory . DIRECTORY_SEPARATOR . 'typephp_pch.hpp';
        $artifact = $this->backend->getPrecompiledHeaderArtifact($headerFile);
        $source = "#pragma once\n";
        foreach ($headers as $header) {
            $source .= '#include <' . $header . ">\n";
        }
        if (!is_file($headerFile) || file_get_contents($headerFile) !== $source) {
            if (file_put_contents($headerFile, $source) === false) {
                throw new \RuntimeException('Cannot write precompiled header: ' . $headerFile);
            }
        }

        if (is_file($artifact)) {
            $this->markUsedAndPrune($cacheDirectory, $directory);
            return ['header' => $headerFile, 'artifact' => $artifact, 'cached' => true, 'command' => ''];
        }

        $result = $this->builder->compile($headerFile, $artifact, $options, 'c++-header', true);
        if ($result['status'] !== 0 || !is_file($artifact)) {
            $message = implode(PHP_EOL, $result['output']);
            throw new \RuntimeException('Failed to build PHPX precompiled header' . ($message === '' ? '' : ': ' . $message));
        }

        $this->markUsedAndPrune($cacheDirectory, $directory);
        return ['header' => $headerFile, 'artifact' => $artifact, 'cached' => false, 'command' => $result['command']];
    }

    private function markUsedAndPrune(string $cacheDirectory, string $currentDirectory): void
    {
        // Cache cleanup is best-effort and must never disable an otherwise
        // usable PCH merely because an old entry cannot be removed.
        @touch($currentDirectory);

        try {
            $entries = $this->getCacheEntries($cacheDirectory, $currentDirectory);
            $cutoff = time() - self::MAX_CACHE_AGE;
            foreach ($entries as $index => $entry) {
                if ($entry['mtime'] < $cutoff || $index >= self::MAX_CACHE_ENTRIES - 1) {
                    $this->removeCacheDirectory($entry['path']);
                }
            }
        } catch (\Throwable) {
            // Ignore cleanup errors; the active artifact is already valid.
        }
    }

    /** @return list<array{path: string, mtime: int}> */
    private function getCacheEntries(string $cacheDirectory, string $currentDirectory): array
    {
        $entries = [];
        $iterator = new \FilesystemIterator($cacheDirectory, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if (!$entry->isDir() || $entry->isLink() || $path === $currentDirectory
                || preg_match('/^[a-f0-9]{24}$/D', $entry->getFilename()) !== 1) {
                continue;
            }
            $entries[] = ['path' => $path, 'mtime' => $entry->getMTime()];
        }
        usort($entries, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return $entries;
    }

    private function removeCacheDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($directory);
    }

    /** @param list<string> $headers @param list<string> $dependencyDirectories */
    private function buildFingerprint(array $headers, array $dependencyDirectories, CompileOptions $options): string
    {
        $compilerVersion = [];
        exec(escapeshellcmd($this->backend->getCompilerCommand()) . ' --version 2>&1', $compilerVersion);
        $context = hash_init('sha256');
        hash_update($context, $this->backend::class . "\0" . implode("\n", $compilerVersion) . "\0");
        $optionValues = $options->toArray();
        // prof_output only affects code when profiling is enabled. Keeping a
        // target-specific inactive filename here would defeat PCH reuse across
        // projects with otherwise identical native build configurations.
        if (empty($optionValues['enable_profiler'])) {
            unset($optionValues['prof_output']);
        }
        unset($optionValues['precompiled_header']);
        hash_update($context, serialize($optionValues) . "\0" . implode("\0", $headers));

        $files = [];
        foreach ($dependencyDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || preg_match('/\.(?:h|hh|hpp|hxx|inc)$/i', $file->getFilename()) !== 1) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            hash_update($context, $file . "\0");
            if (!hash_update_file($context, $file)) {
                throw new \RuntimeException('Cannot fingerprint precompiled-header dependency: ' . $file);
            }
            hash_update($context, "\0");
        }

        return substr(hash_final($context), 0, 24);
    }
}
