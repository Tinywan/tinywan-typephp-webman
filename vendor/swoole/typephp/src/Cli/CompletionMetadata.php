<?php

namespace TypePhp\Cli;

use TypePhp\Metadata\Constants;

final class CompletionMetadata
{
    private const array HIDDEN_OPTIONS = ['debug-line'];

    /** @return list<string> */
    public static function options(): array
    {
        $options = [];
        foreach (Constants::COMPILER_OPTIONS as $name => $definition) {
            if (in_array($name, self::HIDDEN_OPTIONS, true)) {
                continue;
            }
            if (isset($definition['prefix'])) {
                $options[] = '-' . $definition['prefix'];
            }
            if (isset($definition['longPrefix'])) {
                $options[] = '--' . $definition['longPrefix'];
            }
        }

        return array_values(array_unique([
            ...$options,
            '--wasm',
            '--wasm=',
            '--gen-python-helper',
            '--convert-python-to-php',
            '--output-dir',
            '--output-dir=',
            '--build-dir=',
            '--generate-completion=',
        ]));
    }

    /** @return array<string, list<string>> */
    public static function values(): array
    {
        return [
            '-O' => ['0', '1', '2', '3'],
            '--optimize' => ['0', '1', '2', '3'],
            '-m' => ['bin', 'lib', 'ext'],
            '--mode' => ['bin', 'lib', 'ext'],
            '--php-version' => ['8.4', '8.5'],
            '--cxx-std' => ['c++17', 'c++20', 'c++23'],
            '--sanitize' => ['address', 'undefined'],
            '--wasm=' => ['component', 'browser'],
            '--generate-completion=' => ['bash'],
        ];
    }

    /** @return list<string> */
    public static function directoryOptions(): array
    {
        return ['--build-dir', '--output-dir', '-I', '--include-path', '-L', '--link-path'];
    }

    /** @return list<string> */
    public static function pythonFileOptions(): array
    {
        return ['--convert-python-to-php'];
    }

    /** @return list<string> */
    public static function outputFileOptions(): array
    {
        return ['-o', '--output'];
    }

    /** @return list<string> */
    public static function directoryEqualsOptions(): array
    {
        return ['--build-dir=', '--output-dir='];
    }
}
