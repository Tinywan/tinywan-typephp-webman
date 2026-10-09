<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final readonly class CompilerRuntime
{
    private function __construct(
        public string $installationRoot,
        public string $executable,
        public bool $sourceEntry,
    ) {
    }

    public static function source(string $installationRoot, string $argument): self
    {
        return new self(
            self::directory($installationRoot),
            ExecutableLocator::resolve($argument) ?? $argument,
            true,
        );
    }

    public static function native(string $argument): self
    {
        $executable = ExecutableLocator::resolve($argument) ?? $argument;
        return self::nativeAt(dirname($executable), $executable);
    }

    public static function nativeAt(string $installationRoot, string $executable): self
    {
        return new self(self::directory($installationRoot), $executable, false);
    }

    private static function directory(string $path): string
    {
        return realpath($path) ?: $path;
    }
}
