<?php

namespace TypePhp\Build;

/**
 * Persistent Make-style dependency graph for generated PHP translation units.
 *
 * Edges point from a PHP source to the PHP sources whose declarations it uses.
 * Dirty propagation therefore walks the reverse graph. The walk is deliberately
 * iterative and visited-based so mutually dependent files form an ordinary
 * strongly-connected component instead of a recursion problem.
 */
final class IncrementalBuildState
{
    private const int SCHEMA_VERSION = 1;

    /** @var array<string, array<string, mixed>> */
    private array $files = [];
    private string $generatorFingerprint = '';

    public function __construct(private readonly string $file)
    {
        $this->restore();
    }

    /** @return null|array<string, mixed> */
    public function metadata(string $source): ?array
    {
        return $this->files[$source] ?? null;
    }

    /**
     * @param array<string, string> $sourceHashes
     * @param array<string, list<string>> $dependencies
     * @param callable(string, ?array<string, mixed>): bool $artifactsExist
     * @return array<string, true>
     */
    public function dirtyFiles(
        array $sourceHashes,
        array $dependencies,
        string $generatorFingerprint,
        callable $artifactsExist,
        bool $force = false,
    ): array {
        $currentFiles = array_fill_keys(array_keys($sourceHashes), true);
        if ($force || $this->generatorFingerprint !== $generatorFingerprint) {
            return $currentFiles;
        }

        $dirty = [];
        foreach ($sourceHashes as $source => $hash) {
            $metadata = $this->files[$source] ?? null;
            $previousDependencies = is_array($metadata['dependencies'] ?? null)
                ? array_values(array_filter($metadata['dependencies'], 'is_string'))
                : [];
            $currentDependencies = $dependencies[$source] ?? [];
            sort($previousDependencies, SORT_STRING);
            sort($currentDependencies, SORT_STRING);
            if (!is_array($metadata)
                || ($metadata['hash'] ?? null) !== $hash
                || $previousDependencies !== $currentDependencies
                || !$artifactsExist($source, $metadata)) {
                $dirty[$source] = true;
            }
        }

        // A removed source is a dirty dependency node as well. Consumers found
        // through the previous graph must be regenerated so a removed/moved
        // declaration cannot remain embedded in an old translation unit.
        foreach ($this->files as $source => $_metadata) {
            if (!isset($currentFiles[$source])) {
                $dirty[$source] = true;
            }
        }

        $reverse = [];
        foreach ([$this->previousDependencies(), $dependencies] as $graph) {
            foreach ($graph as $consumer => $requirements) {
                foreach ($requirements as $requirement) {
                    $reverse[$requirement][$consumer] = true;
                }
            }
        }

        $queue = array_keys($dirty);
        for ($offset = 0; isset($queue[$offset]); ++$offset) {
            $dependency = $queue[$offset];
            foreach ($reverse[$dependency] ?? [] as $consumer => $_) {
                if (isset($dirty[$consumer])) {
                    continue;
                }
                $dirty[$consumer] = true;
                $queue[] = $consumer;
            }
        }

        $dirty = array_intersect_key($dirty, $currentFiles);
        ksort($dirty, SORT_STRING);
        return $dirty;
    }

    /**
     * @param array<string, array<string, mixed>> $files
     */
    public function save(string $generatorFingerprint, array $files): void
    {
        if ($this->file === '') {
            $this->generatorFingerprint = $generatorFingerprint;
            $this->files = $files;
            return;
        }
        ksort($files, SORT_STRING);
        $contents = json_encode([
            'schema' => self::SCHEMA_VERSION,
            'generatorFingerprint' => $generatorFingerprint,
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        AtomicFile::write($this->file, $contents, '.graph-');
        $this->generatorFingerprint = $generatorFingerprint;
        $this->files = $files;
    }

    /** @return array<string, list<string>> */
    private function previousDependencies(): array
    {
        $dependencies = [];
        foreach ($this->files as $source => $metadata) {
            $values = is_array($metadata['dependencies'] ?? null)
                ? array_values(array_filter($metadata['dependencies'], 'is_string'))
                : [];
            $dependencies[$source] = $values;
        }
        return $dependencies;
    }

    private function restore(): void
    {
        if ($this->file === '' || !is_file($this->file)) {
            return;
        }
        try {
            $contents = file_get_contents($this->file);
            $payload = is_string($contents)
                ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR)
                : null;
            if (!is_array($payload)
                || ($payload['schema'] ?? null) !== self::SCHEMA_VERSION
                || !is_string($payload['generatorFingerprint'] ?? null)
                || !is_array($payload['files'] ?? null)) {
                return;
            }
            foreach ($payload['files'] as $source => $metadata) {
                if (!is_string($source)
                    || !is_array($metadata)
                    || !is_string($metadata['hash'] ?? null)
                    || !is_array($metadata['dependencies'] ?? null)) {
                    return;
                }
            }
            $this->generatorFingerprint = $payload['generatorFingerprint'];
            $this->files = $payload['files'];
        } catch (\Throwable) {
            $this->files = [];
            $this->generatorFingerprint = '';
        }
    }
}
