<?php

namespace TypePhp\Config;

use Closure;
use Symfony\Component\Yaml\Yaml;

final class ProjectYamlLoader
{
    /** @param Closure(string): never $error */
    public function __construct(
        private string $phpVersion,
        private readonly Closure $error,
        private readonly string $osFamily = PHP_OS_FAMILY,
    ) {
    }

    public function setPhpVersion(string $phpVersion): void
    {
        $this->phpVersion = $phpVersion;
    }

    public function load(string $path): array
    {
        $config = Yaml::parseFile($path);
        if (!is_array($config)) {
            ($this->error)('Project YAML root must be a map');
        }
        return $config;
    }

    /** @return array{0: string, 1: string|null} */
    public function parseSourceEntry(mixed $entry): array
    {
        if (is_string($entry)) {
            return [$entry, null];
        }
        if (!is_array($entry)) {
            ($this->error)('Each `sources` entry must be a string or map');
        }
        $path = $entry['path'] ?? $entry['source'] ?? $entry['file'] ?? null;
        if (!is_string($path) || trim($path) === '') {
            ($this->error)('Conditional `sources` entries must include a non-empty `path`');
        }
        $condition = $entry['if'] ?? $entry['when'] ?? null;
        if ($condition !== null && !is_string($condition)) {
            ($this->error)('Source condition must be a string');
        }
        return [$path, $condition];
    }

    public function evaluateCondition(string $condition): bool
    {
        $condition = trim($condition);
        if ($condition === '') {
            ($this->error)('Source condition must not be empty');
        }
        $expr = $this->replacePhpVersionComparisons($condition);
        $expr = $this->replaceOsFamilyComparisons($expr, $condition);
        if (preg_match('/[A-Za-z_]/', $expr)
            || !preg_match('/^[0-9\s<>=!&|().+-]+$/', $expr)
            || preg_match('/(?<![&])&(?!&)|(?<![|])\|(?!\|)/', $expr)) {
            ($this->error)('Unsupported source condition: `' . $condition . '`');
        }
        try {
            return (bool) eval('return (' . $expr . ');');
        } catch (\Throwable) {
            ($this->error)('Invalid source condition: `' . $condition . '`');
        }
    }

    private function replacePhpVersionComparisons(string $condition): string
    {
        $literal = '"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'';
        $operator = '(>=|<=|==|!=|<>|=|>|<|lt|le|gt|ge|eq|ne)';
        $patterns = [
            '/\bPHP_VERSION_ID\b\s*' . $operator . '\s*([0-9]+)/i' => fn(array $m): bool => version_compare($this->phpVersion, $this->versionIdToString((int) $m[2]), strtolower($m[1])),
            '/([0-9]+)\s*' . $operator . '\s*\bPHP_VERSION_ID\b/i' => fn(array $m): bool => version_compare($this->versionIdToString((int) $m[1]), $this->phpVersion, strtolower($m[2])),
            '/\bPHP_VERSION\b\s*' . $operator . '\s*(' . $literal . ')/i' => function (array $m): bool {
                $version = stripcslashes(($m[3] ?? '') !== '' ? $m[3] : $m[4]);
                $this->assertVersion($version);
                return version_compare($this->phpVersion, $version, strtolower($m[1]));
            },
            '/(' . $literal . ')\s*' . $operator . '\s*\bPHP_VERSION\b/i' => function (array $m): bool {
                $version = stripcslashes($m[2] !== '' ? $m[2] : $m[3]);
                $this->assertVersion($version);
                return version_compare($version, $this->phpVersion, strtolower($m[4]));
            },
        ];
        $expr = $condition;
        foreach ($patterns as $pattern => $compare) {
            $expr = preg_replace_callback($pattern, fn(array $m): string => $compare($m) ? '1' : '0', $expr);
            if ($expr === null) {
                ($this->error)('Invalid source condition: `' . $condition . '`');
            }
        }
        if (preg_match('/\bPHP_VERSION(?:_ID)?\b/', $expr)) {
            ($this->error)('Unsupported source condition: `' . $condition . '`');
        }
        return $expr;
    }

    private function replaceOsFamilyComparisons(string $expr, string $condition): string
    {
        $literal = '"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'';
        $expr = preg_replace_callback('/\bPHP_OS_FAMILY\b\s*(==|!=)\s*(' . $literal . ')/i', function (array $m): string {
            $expected = stripcslashes(($m[3] ?? '') !== '' ? $m[3] : $m[4]);
            $this->assertOsFamily($expected);
            return (($this->osFamily === $expected) xor ($m[1] === '!=')) ? '1' : '0';
        }, $expr);
        $expr = preg_replace_callback('/(' . $literal . ')\s*(==|!=)\s*\bPHP_OS_FAMILY\b/i', function (array $m): string {
            $expected = stripcslashes($m[2] !== '' ? $m[2] : $m[3]);
            $this->assertOsFamily($expected);
            return (($expected === $this->osFamily) xor ($m[4] === '!=')) ? '1' : '0';
        }, $expr ?? '');
        if ($expr === null || preg_match('/\bPHP_OS_FAMILY\b/', $expr)) {
            ($this->error)('Unsupported source condition: `' . $condition . '`');
        }
        return $expr;
    }

    private function assertVersion(string $version): void
    {
        if ($version === '' || !preg_match('/^[0-9A-Za-z_.+\-]+$/', $version)) {
            ($this->error)('Invalid PHP_VERSION literal: `' . $version . '`');
        }
    }

    private function assertOsFamily(string $osFamily): void
    {
        if (!in_array($osFamily, ['Windows', 'BSD', 'Darwin', 'Solaris', 'Linux', 'Unknown'], true)) {
            ($this->error)('Invalid PHP_OS_FAMILY literal: `' . $osFamily . '`');
        }
    }

    private function versionIdToString(int $versionId): string
    {
        return intdiv($versionId, 10000) . '.' . intdiv($versionId % 10000, 100) . '.' . ($versionId % 100);
    }
}
