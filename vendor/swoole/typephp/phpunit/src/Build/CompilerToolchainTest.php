<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Backend\Clang;
use TypePhp\Backend\Gcc;
use TypePhp\Backend\Msvc;
use TypePhp\Build\CompilerToolchain;
use TypePhp\Platform\Linux;
use TypePhp\Platform\Macos;
use TypePhp\Platform\Windows;

/**
 * @internal
 * @coversNothing
 */
final class CompilerToolchainTest extends TestCase
{
    public function testDerivesCrossGccCompanionTools(): void
    {
        $toolchain = CompilerToolchain::fromBackend(new Gcc(
            new Linux(),
            '/opt/cross/bin/aarch64-linux-gnu-g++',
        ));

        self::assertSame('/opt/cross/bin/aarch64-linux-gnu-gcc', $toolchain->cCompiler);
        self::assertSame('/opt/cross/bin/aarch64-linux-gnu-g++', $toolchain->cxxCompiler);
        self::assertSame('/opt/cross/bin/aarch64-linux-gnu-gcc-ar', $toolchain->archiver);
        self::assertSame(CompilerToolchain::ARCHIVER_UNIX, $toolchain->archiverStyle);
    }

    public function testDerivesVersionedClangCompanionTools(): void
    {
        $toolchain = CompilerToolchain::fromBackend(new Clang(
            new Macos(),
            '/opt/llvm/bin/clang++-18',
        ));

        self::assertSame('/opt/llvm/bin/clang-18', $toolchain->cCompiler);
        self::assertSame('/opt/llvm/bin/clang++-18', $toolchain->cxxCompiler);
        self::assertSame('/opt/llvm/bin/llvm-ar-18', $toolchain->archiver);
    }

    public function testSelectsMsvcLibraryManager(): void
    {
        $toolchain = CompilerToolchain::fromBackend(new Msvc(new Windows(), 'cl'));

        self::assertSame('cl', $toolchain->cCompiler);
        self::assertSame('cl', $toolchain->cxxCompiler);
        self::assertContains(strtolower(basename($toolchain->archiver)), ['lib', 'lib.exe']);
        self::assertSame(CompilerToolchain::ARCHIVER_MSVC, $toolchain->archiverStyle);
    }
}
