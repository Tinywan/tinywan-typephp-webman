<?php

namespace TypePhp\Build;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final readonly class WasiProjectConfig
{
    private function __construct(
        public string $input,
        public string $buildDir,
        public ?string $output,
        public ?string $browserDir,
        public string $profile,
        public string $mode,
        public string $package,
        public string $world,
    ) {
    }

    public static function load(
        string $input,
        ?string $cliBuildDir,
        string $workingDirectory,
        string $defaultBuildDir,
        ?string $cliProfile = null,
    ): self {
        $input = self::absolutePath($input, $workingDirectory);
        $realInput = realpath($input);
        if ($realInput === false || !is_file($realInput)) {
            throw new RuntimeException("WASI input does not exist: {$input}");
        }

        $projectDir = dirname($realInput);
        $config = null;
        if (preg_match('/\.ya?ml$/i', $realInput) === 1) {
            $config = Yaml::parseFile($realInput);
            if (!is_array($config)) {
                throw new RuntimeException('WASI project YAML root must be a map');
            }
        }

        $buildDir = $cliBuildDir;
        if ($buildDir === null && is_array($config) && !empty($config['build-dir'])) {
            $buildDir = (string) $config['build-dir'];
            $buildDir = self::absolutePath($buildDir, $projectDir);
        }
        $buildDir ??= $defaultBuildDir;
        $buildDir = self::absolutePath($buildDir, $workingDirectory);

        if (!is_array($config)) {
            return new self(
                $realInput,
                $buildDir,
                null,
                null,
                self::normalizeProfile($cliProfile ?? 'component'),
                'command',
                'typephp:app@1.0.0',
                'app',
            );
        }

        $target = (string) ($config['target-platform'] ?? 'wasm32-wasip2');
        if (!in_array($target, ['wasm32-wasip2', 'wasm32-unknown-wasip2'], true)) {
            throw new RuntimeException('A WASI project must target wasm32-wasip2');
        }

        $mode = strtolower((string) ($config['wasm-mode'] ?? $config['mode'] ?? $config['build-mode'] ?? $config['type'] ?? 'command'));
        $mode = match ($mode) {
            'bin', 'binary', 'cli' => 'command',
            'lib', 'library', 'reactor' => 'library',
            default => $mode,
        };
        if (!in_array($mode, ['command', 'library'], true)) {
            throw new RuntimeException('A WASI project mode must be `command` or `library`');
        }

        $name = trim((string) ($config['name'] ?? 'app'));
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException('A WASI project name must be a non-empty file name');
        }
        if (!empty($config['output'])) {
            $output = self::absolutePath((string) $config['output'], $projectDir);
            $extension = pathinfo($output, PATHINFO_EXTENSION);
            if ($extension === '') {
                $output .= '.wasm';
            } elseif (strcasecmp($extension, 'wasm') !== 0) {
                throw new RuntimeException('A WASI project output must use the .wasm extension');
            }
        } else {
            $output = $projectDir . DIRECTORY_SEPARATOR . $name . '.wasm';
        }

        $configProfile = 'component';
        if (array_key_exists('wasm', $config)) {
            if (!is_string($config['wasm'])) {
                throw new RuntimeException('The `wasm` project option must be `component` or `browser`');
            }
            $configProfile = $config['wasm'];
        }
        $profile = self::normalizeProfile($cliProfile ?? $configProfile);

        $browserPath = $config['wasm-browser-dir'] ?? null;
        $browserDir = $profile === 'browser' && !empty($browserPath)
            ? self::absolutePath((string) $browserPath, $projectDir)
            : null;

        $package = strtolower(trim((string) ($config['wasm-package'] ?? 'typephp:' . $name . '@1.0.0')));
        if (preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*@[0-9]+\.[0-9]+\.[0-9]+$/', $package) !== 1) {
            throw new RuntimeException('`wasm-package` must use the WIT form namespace:name@major.minor.patch');
        }
        $world = strtolower(trim((string) ($config['wasm-world'] ?? $name)));
        $world = str_replace('_', '-', $world);
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $world) !== 1) {
            throw new RuntimeException('`wasm-world` must be a lowercase WIT identifier');
        }

        return new self($realInput, $buildDir, $output, $browserDir, $profile, $mode, $package, $world);
    }

    public static function isWasmEnabled(string $path): bool
    {
        if (!is_file($path) || preg_match('/\.ya?ml$/i', $path) !== 1) {
            return false;
        }
        try {
            $config = Yaml::parseFile($path);
        } catch (\Throwable) {
            return false;
        }
        if (!is_array($config) || !array_key_exists('wasm', $config)) {
            return false;
        }
        return true;
    }

    private static function normalizeProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        if (!in_array($profile, ['browser', 'component'], true)) {
            throw new RuntimeException("Unsupported WASI output profile `{$profile}`; expected browser or component");
        }
        return $profile;
    }

    private static function absolutePath(string $path, string $baseDirectory): string
    {
        if ($path === '') {
            throw new RuntimeException('WASI project paths must not be empty');
        }
        if ($path[0] === '/' || $path[0] === '\\' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }
        return rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR . $path;
    }
}
