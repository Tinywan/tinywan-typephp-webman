<?php

namespace TypePhp\Build;

use RuntimeException;

final class WasiToolchain
{
    public const MIN_LLVM_MAJOR = 22;
    public const MIN_WASMTIME_MAJOR = 47;
    public const MIN_JCO_MAJOR = 1;
    public const WIT_BINDGEN_VERSION = '0.60.0';

    /** @return array<string, string> */
    public function detect(bool $requireBrowserTools = true, bool $requireWitBindgen = false): array
    {
        $tools = [];
        $requiredTools = [
            'wasm32-wasip2-clang',
            'wasm32-wasip2-clang++',
            'llvm-ar',
            'llvm-ranlib',
            'llvm-nm',
            'wasm-component-ld',
            'wasmtime',
        ];
        if ($requireBrowserTools) {
            $requiredTools[] = 'jco';
        }
        if ($requireWitBindgen) {
            $requiredTools[] = 'wit-bindgen';
        }
        foreach ($requiredTools as $name) {
            $tools[$name] = $this->findExecutable($name);
        }

        $versions = [];
        foreach (['wasm32-wasip2-clang', 'wasm32-wasip2-clang++', 'llvm-ar', 'llvm-ranlib', 'llvm-nm'] as $name) {
            $versions[$name] = $this->requireVersion($name, $tools[$name], self::MIN_LLVM_MAJOR);
        }
        $this->requireVersion('wasm-component-ld', $tools['wasm-component-ld'], 0);
        $versions['wasmtime'] = $this->requireVersion('wasmtime', $tools['wasmtime'], self::MIN_WASMTIME_MAJOR);
        if ($requireBrowserTools) {
            $versions['jco'] = $this->requireVersion('jco', $tools['jco'], self::MIN_JCO_MAJOR);
        }
        if ($requireWitBindgen) {
            [$exitCode, $output, $error] = $this->run([$tools['wit-bindgen'], '--version']);
            $version = trim($output . "\n" . $error);
            $expectedVersion = 'wit-bindgen-cli ' . self::WIT_BINDGEN_VERSION;
            if ($exitCode !== 0 || $version !== $expectedVersion) {
                throw new RuntimeException(
                    "WASI tool `wit-bindgen` has an incompatible version: "
                    . ($version !== '' ? $version : 'unknown')
                    . "; {$expectedVersion} is required",
                );
            }
            $versions['wit-bindgen'] = self::WIT_BINDGEN_VERSION;
        }

        [$exitCode, $target, $error] = $this->run([$tools['wasm32-wasip2-clang++'], '--print-target-triple']);
        $target = trim($target);
        if ($exitCode !== 0 || $target !== 'wasm32-unknown-wasip2') {
            $detail = trim($error) !== '' ? ': ' . trim($error) : '';
            throw new RuntimeException(
                "wasm32-wasip2-clang++ from PATH is not configured for wasm32-unknown-wasip2 (reported target: "
                . ($target !== '' ? $target : 'unknown') . "){$detail}",
            );
        }

        $tools['clang'] = $tools['wasm32-wasip2-clang'];
        $tools['clang++'] = $tools['wasm32-wasip2-clang++'];
        $tools['wasm-ld'] = $tools['wasm-component-ld'];
        $tools['target'] = $target;
        $tools['clang-version'] = $versions['wasm32-wasip2-clang++'];
        $tools['wasmtime-version'] = $versions['wasmtime'];
        if ($requireBrowserTools) {
            $tools['jco-version'] = $versions['jco'];
        }
        if ($requireWitBindgen) {
            $tools['wit-bindgen-version'] = $versions['wit-bindgen'];
        }
        return $tools;
    }

    private function findExecutable(string $name): string
    {
        $path = getenv('PATH');
        foreach (explode(PATH_SEPARATOR, is_string($path) ? $path : '') as $directory) {
            if ($directory === '') {
                continue;
            }
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                // Preserve the PATH entry instead of resolving symlinks. LLVM
                // multicall binaries select their driver mode and adjacent
                // .cfg file from argv[0] (notably clang++ and wasm-ld).
                return $candidate;
            }
        }

        throw new RuntimeException("Required WASI tool `{$name}` was not found in PATH");
    }

    private function requireVersion(string $name, string $executable, int $minimumMajor): string
    {
        [$exitCode, $output, $error] = $this->run([$executable, '--version']);
        $versionText = trim($output . "\n" . $error);
        if ($exitCode !== 0 || preg_match('/\bv?((\d+)(?:\.\d+)+)\b/i', $versionText, $match) !== 1) {
            throw new RuntimeException("Unable to determine the version of WASI tool `{$name}` from PATH");
        }
        $major = (int) $match[2];
        if ($major < $minimumMajor) {
            throw new RuntimeException(
                "WASI tool `{$name}` {$major} is too old; version {$minimumMajor} or newer is required",
            );
        }
        return $match[1];
    }

    /** @return array{int, string, string} */
    private function run(array $command): array
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            return [127, '', 'failed to start process'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
