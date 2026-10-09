<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

use TypePhp\Backend\Clang;
use TypePhp\Backend\CompilerBackend;
use TypePhp\Backend\Gcc;
use TypePhp\Backend\Msvc;
use TypePhp\Installer\PhpBuildConfiguration;

final readonly class CompilerToolchain
{
    public const string ARCHIVER_UNIX = 'unix';
    public const string ARCHIVER_MSVC = 'msvc';

    public function __construct(
        public string $cCompiler,
        public string $cxxCompiler,
        public string $archiver,
        public string $archiverStyle,
    ) {
    }

    public static function fromBackend(CompilerBackend $backend): self
    {
        $cxx = $backend->getCompilerCommand();
        [$words, $compilerIndex] = self::compilerWords($cxx);
        $compiler = $words[$compilerIndex];

        if ($backend instanceof Gcc) {
            $cCompiler = self::replaceCompilerWord($words, $compilerIndex, self::gccCCompiler($compiler));
            $archiver = self::resolveCompanion($compiler, self::gccArchiverNames($compiler));
            return new self($cCompiler, $cxx, $archiver, self::ARCHIVER_UNIX);
        }
        if ($backend instanceof Clang) {
            $cCompiler = self::replaceCompilerWord($words, $compilerIndex, self::clangCCompiler($compiler));
            $archiver = self::resolveCompanion($compiler, self::clangArchiverNames($compiler));
            return new self($cCompiler, $cxx, $archiver, self::ARCHIVER_UNIX);
        }
        if ($backend instanceof Msvc) {
            $archiver = self::resolveCompanion($compiler, ['lib.exe', 'lib']);
            return new self($cxx, $cxx, $archiver, self::ARCHIVER_MSVC);
        }
        throw new \RuntimeException('Unsupported PHP builder compiler toolchain: ' . $backend->getName());
    }

    /** @return array{list<string>, int} */
    private static function compilerWords(string $command): array
    {
        $words = PhpBuildConfiguration::parseShellWords($command);
        if ($words === []) {
            throw new \RuntimeException('The compiler command is empty');
        }
        for ($index = count($words) - 1; $index >= 0; --$index) {
            $name = strtolower(preg_replace('/\.exe$/i', '', basename(str_replace('\\', '/', $words[$index]))) ?? '');
            if (preg_match('/(?:^|-)clang(?:\+\+|-cl)?(?:-\d+)?$|(?:^|-)(?:g\+\+|gcc|c\+\+|cl)(?:-\d+)?$/', $name) === 1) {
                return [$words, $index];
            }
        }
        return [$words, 0];
    }

    /** @param list<string> $words */
    private static function replaceCompilerWord(array $words, int $index, string $compiler): string
    {
        $words[$index] = $compiler;
        if (count($words) === 1) {
            return $compiler;
        }
        return implode(' ', array_map('escapeshellarg', $words));
    }

    private static function gccCCompiler(string $compiler): string
    {
        return self::replaceBasename($compiler, static function (string $name): string {
            $name = preg_replace('/g\+\+/', 'gcc', $name, 1, $count) ?? $name;
            if ($count === 0) {
                $name = preg_replace('/c\+\+/', 'cc', $name, 1) ?? $name;
            }
            return $name;
        });
    }

    private static function clangCCompiler(string $compiler): string
    {
        return self::replaceBasename(
            $compiler,
            static fn (string $name): string => preg_replace('/clang\+\+/', 'clang', $name, 1) ?? $name,
        );
    }

    /** @return list<string> */
    private static function gccArchiverNames(string $compiler): array
    {
        $name = basename(str_replace('\\', '/', $compiler));
        $gccAr = preg_replace('/g\+\+|gcc|c\+\+/', 'gcc-ar', $name, 1, $count) ?? $name;
        if ($count === 0) {
            $gccAr = 'gcc-ar';
        }
        $plainAr = preg_replace('/g\+\+|gcc|c\+\+/', 'ar', $name, 1, $plainCount) ?? $name;
        if ($plainCount === 0) {
            $plainAr = 'ar';
        }
        return array_values(array_unique([$gccAr, 'gcc-ar', $plainAr, 'ar']));
    }

    /** @return list<string> */
    private static function clangArchiverNames(string $compiler): array
    {
        $name = strtolower(preg_replace('/\.exe$/i', '', basename(str_replace('\\', '/', $compiler))) ?? '');
        $suffix = preg_match('/clang(?:\+\+)?-(\d+)$/', $name, $match) === 1 ? '-' . $match[1] : '';
        $names = ['llvm-ar' . $suffix, 'llvm-ar'];
        if (PHP_OS_FAMILY === 'Darwin') {
            // Xcode ships the LLVM archiver as /usr/bin/ar on some releases.
            $names[] = 'ar';
        }
        return array_values(array_unique($names));
    }

    /** @param list<string> $names */
    private static function resolveCompanion(string $compiler, array $names): string
    {
        $normalized = str_replace('\\', '/', $compiler);
        $hasDirectory = str_contains($normalized, '/');
        if ($hasDirectory) {
            $directory = dirname($normalized);
            foreach ($names as $name) {
                $resolved = ExecutableLocator::resolve($directory . DIRECTORY_SEPARATOR . $name);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
            // An explicitly selected compiler directory defines the toolchain
            // boundary. Do not silently take an unrelated archiver from PATH.
            return $directory . DIRECTORY_SEPARATOR . $names[0];
        }
        foreach ($names as $name) {
            $resolved = ExecutableLocator::resolve($name);
            if ($resolved !== null) {
                return $resolved;
            }
        }
        return $names[0];
    }

    private static function replaceBasename(string $path, callable $replace): string
    {
        $normalized = str_replace('\\', '/', $path);
        $name = $replace(basename($normalized));
        if (!str_contains($normalized, '/')) {
            return $name;
        }
        return dirname($normalized) . DIRECTORY_SEPARATOR . $name;
    }
}
