<?php

namespace TypePhp\Build;

use Composer\InstalledVersions;
use PhpParser\Node;
use PhpParser\Parser;

/**
 * Persistent cache of pristine php-parser output.
 *
 * Every load returns an independent AST. The cached tree is serialized before
 * any TypePHP visitor can mutate it, so prepare and convert retain their
 * existing phase-local lowering semantics without parsing the source twice.
 */
final class AstCache
{
    private const int SCHEMA_VERSION = 1;

    private readonly string $cacheDirectory;
    private readonly string $parserVersion;

    public function __construct(
        private readonly Parser $parser,
        string $buildDirectory,
        private readonly string $phpVersion,
        ?string $parserVersion = null,
    ) {
        $this->cacheDirectory = rtrim($buildDirectory, '/\\')
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'ast';
        $this->parserVersion = $parserVersion ?? self::detectParserVersion();
    }

    /** @return list<Node\Stmt> */
    public function load(string $file, string $source): array
    {
        $cacheFile = $this->getCacheFile($file);
        $sourceHash = hash('sha256', $source);
        $cached = $this->restore($file, $cacheFile, $source, $sourceHash);
        if ($cached !== null) {
            return $cached;
        }

        $ast = $this->parser->parse($source);
        if ($ast === null) {
            throw new \LogicException('php-parser returned no AST for: ' . $file);
        }
        $ast = $this->requireStatementList($ast);
        $this->store($cacheFile, $source, $sourceHash, $ast);
        return $ast;
    }

    /** @return list<Node\Stmt>|null */
    private function restore(string $file, string $cacheFile, string $source, string $sourceHash): ?array
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        clearstatcache(true, $file);
        clearstatcache(true, $cacheFile);
        $sourceMtime = filemtime($file);
        $cacheMtime = filemtime($cacheFile);
        if ($sourceMtime === false || $cacheMtime === false || $sourceMtime > $cacheMtime) {
            return null;
        }

        $contents = file_get_contents($cacheFile);
        if (!is_string($contents) || $contents === '') {
            return null;
        }

        try {
            $payload = @unserialize($contents, ['allowed_classes' => true]);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($payload)
            || ($payload['schema'] ?? null) !== self::SCHEMA_VERSION
            || ($payload['phpVersion'] ?? null) !== $this->phpVersion
            || ($payload['parserVersion'] ?? null) !== $this->parserVersion
            || ($payload['sourceSize'] ?? null) !== strlen($source)
            || ($payload['sourceHash'] ?? null) !== $sourceHash
            || !isset($payload['ast'])
            || !is_array($payload['ast'])
            || !array_is_list($payload['ast'])
        ) {
            return null;
        }

        try {
            return $this->requireStatementList($payload['ast']);
        } catch (\UnexpectedValueException) {
            return null;
        }
    }

    /** @param list<Node\Stmt> $ast */
    private function store(string $cacheFile, string $source, string $sourceHash, array $ast): void
    {
        try {
            $contents = serialize([
                'schema' => self::SCHEMA_VERSION,
                'phpVersion' => $this->phpVersion,
                'parserVersion' => $this->parserVersion,
                'sourceSize' => strlen($source),
                'sourceHash' => $sourceHash,
                'ast' => $ast,
            ]);
            AtomicFile::write($cacheFile, $contents, '.ast-');
        } catch (\Throwable) {
            // AST caching is an optimization. Serialization or filesystem
            // failures must never prevent an otherwise valid compilation.
        }
    }

    private function getCacheFile(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $cacheIdentity = implode("\0", [
            (string) self::SCHEMA_VERSION,
            $this->phpVersion,
            $this->parserVersion,
            $normalized,
        ]);
        return $this->cacheDirectory . DIRECTORY_SEPARATOR
            . hash('sha256', $cacheIdentity) . '.ast';
    }

    /** @param array<mixed> $ast @return list<Node\Stmt> */
    private function requireStatementList(array $ast): array
    {
        foreach ($ast as $node) {
            if (!$node instanceof Node\Stmt) {
                throw new \UnexpectedValueException('AST cache contains a non-statement root node');
            }
        }
        return $ast;
    }

    private static function detectParserVersion(): string
    {
        if (class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('nikic/php-parser')
        ) {
            return InstalledVersions::getPrettyVersion('nikic/php-parser')
                ?? InstalledVersions::getVersion('nikic/php-parser')
                ?? InstalledVersions::getReference('nikic/php-parser')
                ?? 'unknown';
        }
        return 'unknown';
    }
}
