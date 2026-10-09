<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\CompilerRuntime;

/**
 * @internal
 * @coversNothing
 */
final class CompilerRuntimeTest extends TestCase
{
    public function testSourceRuntimeKeepsInstallationRootSeparateFromEntrypoint(): void
    {
        $runtime = CompilerRuntime::source(dirname(__DIR__, 3), PHP_BINARY);

        self::assertTrue($runtime->sourceEntry);
        self::assertSame(realpath(dirname(__DIR__, 3)), $runtime->installationRoot);
        self::assertSame(realpath(PHP_BINARY), $runtime->executable);
    }

    public function testNativeRuntimeUsesExecutableDirectory(): void
    {
        $runtime = CompilerRuntime::native(PHP_BINARY);

        self::assertFalse($runtime->sourceEntry);
        self::assertSame(realpath(PHP_BINARY), $runtime->executable);
        self::assertSame(realpath(dirname(PHP_BINARY)), $runtime->installationRoot);
    }
}
