<?php

namespace TypePhp\Backend;

use TypePhp\Platform\PlatformBase;
use TypePhp\Platform\Windows;
use TypePhp\Platform\Linux;
use TypePhp\Platform\Macos;

/**
 * Compiler factory.
 * Automatically creates the appropriate compiler backend based on the platform.
 */
class CompilerFactory
{
    /**
     * Create the default compiler backend.
     */
    public static function create(PlatformBase $platform): CompilerBackend
    {
        if ($platform instanceof Windows) {
            // Windows uses MSVC by default.
            return new Msvc($platform, $platform->getDefaultCompiler());
        } elseif ($platform instanceof Linux) {
            // Linux uses GCC by default.
            return new Gcc($platform, $platform->getDefaultCompiler());
        } elseif ($platform instanceof Macos) {
            // macOS uses Clang by default.
            return new Clang($platform, $platform->getDefaultCompiler());
        } else {
            throw new \RuntimeException("Unsupported platform: " . $platform->getName());
        }
    }

    /**
     * Resolve the compiler command from an explicit selection, falling back to the platform default.
     */
    public static function detectCompilerName(PlatformBase $platform, string $configuredCompiler = ''): string
    {
        return $configuredCompiler !== '' ? $configuredCompiler : $platform->getDefaultCompiler();
    }

    /**
     * Create a compiler backend of the specified type.
     */
    public static function createByName(string $compilerName, PlatformBase $platform): CompilerBackend
    {
        $normalized = self::normalizeCompilerName($compilerName);
        $lowerCommand = strtolower($compilerName);

        if (str_contains($normalized, 'clang') || str_contains($lowerCommand, 'clang')) {
            $linker = $platform instanceof Windows ? Clang::detectWindowsLinker() : null;
            return new Clang($platform, $compilerName, $linker);
        }

        if (
            $normalized === 'gcc' ||
            $normalized === 'g++' ||
            $normalized === 'c++' ||
            str_ends_with($normalized, '-gcc') ||
            str_ends_with($normalized, '-g++') ||
            str_contains($lowerCommand, 'g++') ||
            str_contains($lowerCommand, 'c++') ||
            str_contains($lowerCommand, 'gcc')
        ) {
            return new Gcc($platform, $compilerName);
        }

        if ($normalized === 'msvc' || $normalized === 'cl') {
            if (!$platform instanceof Windows) {
                throw new \RuntimeException("MSVC compiler is only supported on Windows");
            }
            return new Msvc($platform, $compilerName);
        }

        throw new \RuntimeException("Unsupported compiler: {$compilerName}");
    }

    /**
     * Auto-detect and create the compiler and platform.
     */
    public static function autoDetect(string $compilerName = '', ?PlatformBase $platform = null): array
    {
        // Create the platform.
        $platform ??= \TypePhp\Platform\PlatformFactory::create();

        // Create the compiler.
        $compilerName = self::detectCompilerName($platform, $compilerName);
        $compiler = self::createByName($compilerName, $platform);

        return [
            'platform' => $platform,
            'compiler' => $compiler,
        ];
    }

    public static function isCommandExecutable(string $command): bool
    {
        $program = self::getCommandProgram($command);
        if ($program === '') {
            return false;
        }

        if (self::isPathLikeCommand($program)) {
            return is_file($program) && is_executable($program);
        }

        $path = getenv('PATH');
        if ($path === false || $path === '') {
            return false;
        }

        $extensions = [''];
        if (DIRECTORY_SEPARATOR === '\\') {
            $pathext = getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD';
            $extensions = array_filter(array_map('strtolower', explode(';', $pathext)));
            if (preg_match('/\.[A-Za-z0-9]+$/', $program)) {
                array_unshift($extensions, '');
            }
        }

        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            if ($dir === '') {
                continue;
            }
            foreach ($extensions as $extension) {
                $candidate = rtrim($dir, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . $program . $extension;
                if (is_file($candidate) && is_executable($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a compiler can actually build code for the given target triple.
     *
     * gcc rejects --target outright, and a wasm-only clang (wasi-sdk is often
     * first on PATH in this project) accepts the flag but only fails once it
     * has to create a target machine, so the probe must reach code generation.
     */
    public static function supportsTarget(string $compilerName, string $target): bool
    {
        if (!self::isCommandExecutable($compilerName)) {
            return false;
        }

        $token = @tempnam(sys_get_temp_dir(), 'tpc_target_probe');
        if ($token === false) {
            return false;
        }
        $source = $token . '.c';
        $object = $token . '.o';
        if (@file_put_contents($source, "int main(void) { return 0; }\n") === false) {
            @unlink($token);
            return false;
        }

        $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $command = escapeshellcmd($compilerName)
            . ' ' . escapeshellarg('--target=' . $target)
            . ' -c ' . escapeshellarg($source)
            . ' -o ' . escapeshellarg($object)
            . ' >' . $nullDevice . ' 2>&1';

        $status = 0;
        @exec($command, $output, $status);

        @unlink($source);
        @unlink($object);
        @unlink($token);

        return $status === 0;
    }

    public static function getCommandProgram(string $command): string
    {
        $command = trim($command);
        if ($command === '') {
            return '';
        }

        if ($command[0] === '"' || $command[0] === "'") {
            $quote = $command[0];
            $end = strpos($command, $quote, 1);
            if ($end !== false) {
                return substr($command, 1, $end - 1);
            }
        }

        $firstToken = strtok($command, " \t\r\n");
        return $firstToken === false ? '' : $firstToken;
    }

    private static function normalizeCompilerName(string $compilerName): string
    {
        $firstToken = self::getCommandProgram($compilerName);
        if ($firstToken === '') {
            return '';
        }

        $name = basename(str_replace('\\', '/', $firstToken));
        $name = strtolower($name);

        return preg_replace('/\.exe$/', '', $name);
    }

    private static function isPathLikeCommand(string $program): bool
    {
        return str_contains($program, '/')
            || str_contains($program, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $program) === 1;
    }
}
