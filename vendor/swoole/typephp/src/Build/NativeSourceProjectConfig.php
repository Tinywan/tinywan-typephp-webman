<?php

namespace TypePhp\Build;

use DOMDocument;
use DOMElement;
use RuntimeException;

/** XML configuration for a source-composition native project. */
final readonly class NativeSourceProjectConfig
{
    /**
     * @param list<string> $phpSources
     * @param list<string> $sources Native C and C++ sources.
     */
    private function __construct(
        public string $file,
        public string $name,
        public string $target,
        public string $buildType,
        public string $compiler,
        public string $cCompiler,
        public string $buildDir,
        public string $output,
        public array $phpSources,
        public array $sources,
    ) {
    }

    public static function isNativeProject(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xml' || !is_file($path)) {
            return false;
        }
        $document = new DOMDocument();
        if (!@$document->load($path, LIBXML_NONET | LIBXML_NOBLANKS)) {
            return false;
        }
        return $document->documentElement?->tagName === 'project'
            && strtolower($document->documentElement->getAttribute('mode')) === 'native';
    }

    public static function load(string $path, ?string $buildDirOverride = null): self
    {
        $file = realpath($path);
        if ($file === false) {
            throw new RuntimeException("Native project does not exist: {$path}");
        }

        $document = new DOMDocument();
        if (!@$document->load($file, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException("Unable to parse native project XML: {$file}");
        }
        $root = $document->documentElement;
        if (!$root instanceof DOMElement || $root->tagName !== 'project'
            || strtolower($root->getAttribute('mode')) !== 'native') {
            throw new RuntimeException('Native project root must be `<project mode="native">`');
        }

        $projectDir = dirname($file);
        $name = trim($root->getAttribute('name'));
        if ($name === '') {
            $name = basename($projectDir);
        }
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $name) !== 1) {
            throw new RuntimeException("Invalid native project name: {$name}");
        }

        $target = strtolower(self::elementText($root, 'target') ?? 'native');
        if (!in_array($target, ['native', 'wasip2'], true)) {
            throw new RuntimeException('Native target must be `native` or `wasip2`');
        }
        $buildType = strtolower(self::elementText($root, 'build-type') ?? 'release');
        if (!in_array($buildType, ['release', 'debug'], true)) {
            throw new RuntimeException('Native build-type must be `release` or `debug`');
        }

        $compiler = self::elementText($root, 'compiler')
            ?? ($target === 'wasip2' ? 'wasm32-wasip2-clang++' : 'c++');
        if ($compiler === '' || preg_match('/[\x00-\x1f]/', $compiler)) {
            throw new RuntimeException('Invalid native compiler command');
        }
        $cCompiler = self::elementText($root, 'c-compiler')
            ?? ($target === 'wasip2' ? 'wasm32-wasip2-clang' : 'cc');
        if ($cCompiler === '' || preg_match('/[\x00-\x1f]/', $cCompiler)) {
            throw new RuntimeException('Invalid native C compiler command');
        }

        $configuredBuildDir = $buildDirOverride
            ?? self::elementText($root, 'build-dir')
            ?? 'build/native-' . $target;
        $buildDir = self::absolutePath($projectDir, $configuredBuildDir);
        $outputName = self::elementText($root, 'output')
            ?? ($target === 'wasip2' ? $name . '.wasm' : $name);
        $output = self::absolutePath($buildDir, $outputName);

        $phpSources = [];
        $sources = [];
        foreach ($root->getElementsByTagName('source') as $sourceNode) {
            $source = trim($sourceNode->textContent);
            if ($source === '') {
                throw new RuntimeException('Native project contains an empty `<source>`');
            }
            $resolved = realpath(self::absolutePath($projectDir, $source));
            if ($resolved === false || !is_file($resolved)) {
                throw new RuntimeException("Native project source does not exist: {$source}");
            }
            $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
            if ($extension === 'php') {
                $phpSources[] = $resolved;
                continue;
            }
            if (!in_array($extension, ['c', 'cc', 'cpp', 'cxx'], true)) {
                throw new RuntimeException(
                    "Native project source must be PHP, C, or C++: {$source}"
                );
            }
            $sources[] = $resolved;
        }
        if ($phpSources === [] && $sources === []) {
            throw new RuntimeException('Native project must contain at least one `<source>`');
        }

        return new self(
            $file,
            $name,
            $target,
            $buildType,
            $compiler,
            $cCompiler,
            $buildDir,
            $output,
            array_values(array_unique($phpSources)),
            array_values(array_unique($sources)),
        );
    }

    private static function elementText(DOMElement $root, string $name): ?string
    {
        $nodes = $root->getElementsByTagName($name);
        if ($nodes->length === 0) {
            return null;
        }
        $value = trim($nodes->item(0)?->textContent ?? '');
        return $value === '' ? null : $value;
    }

    private static function absolutePath(string $base, string $path): string
    {
        if ($path[0] === '/' || $path[0] === '\\'
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }
        return $base . DIRECTORY_SEPARATOR . $path;
    }
}
