<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Build\NativeDependencyAuditor;

final class NativeDependencyAuditorTest extends TestCase
{
    public function testToolchainRuntimeLibrariesAreAllowedForWasip2(): void
    {
        (new NativeDependencyAuditor())->assertLinkFlags(
            'wasip2',
            ['-fwasm-exceptions', '-lsetjmp', '-lunwind', '-Wl,--gc-sections'],
        );
        self::addToAssertionCount(1);
    }

    public function testExternalLibraryIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not link external library `-lcurl`');
        (new NativeDependencyAuditor())->assertLinkFlags('native', ['-lcurl']);
    }

    public function testNativeForbiddenSymbolIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mmap');
        (new NativeDependencyAuditor())->assertUndefinedSymbols(
            'native',
            "                 U malloc@GLIBC_2.2.5\n                 U mmap@GLIBC_2.2.5\n",
        );
    }

    /** @dataProvider forbiddenNativeCapabilityProvider */
    public function testNativeCapabilityFamiliesAreRejected(string $symbol): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($symbol);
        (new NativeDependencyAuditor())->assertUndefinedSymbols(
            'native',
            "                 U {$symbol}@GLIBC_2.2.5\n",
        );
    }

    public static function forbiddenNativeCapabilityProvider(): array
    {
        return [
            'process pipe' => ['pipe'],
            'descriptor ioctl' => ['ioctl'],
            'descriptor selection' => ['select'],
            'network socket' => ['socket'],
            'network connection' => ['connect'],
            'system logging' => ['syslog'],
        ];
    }

    public function testWasip2FilesystemImportsAreAllowed(): void
    {
        (new NativeDependencyAuditor())->assertUndefinedSymbols(
            'wasip2',
            "         U __imported_wasi_snapshot_preview1_fd_write\n"
            . "         U __imported_wasi_snapshot_preview1_fd_read\n"
            . "         U __imported_wasi_snapshot_preview1_path_open\n",
        );
        self::addToAssertionCount(1);
    }

    public function testNativeFilesystemSymbolsAreAllowed(): void
    {
        (new NativeDependencyAuditor())->assertUndefinedSymbols(
            'native',
            "                 U fopen@GLIBC_2.2.5\n"
            . "                 U stat@GLIBC_2.2.5\n"
            . "                 U read@GLIBC_2.2.5\n",
        );
        self::addToAssertionCount(1);
    }

    public function testOnlyNonPosixExceptionsNeedAnAllowlistEntry(): void
    {
        self::assertContains('flock', NativeDependencyAuditor::NON_POSIX_HOST_FUNCTION_ALLOWLIST);
        foreach (['fopen', 'strdup', 'dup', 'dup2', 'mkstemp', 'chown', 'getuid'] as $standardName) {
            self::assertNotContains(
                $standardName,
                NativeDependencyAuditor::NON_POSIX_HOST_FUNCTION_ALLOWLIST,
            );
        }
        self::assertNotContains('socket', NativeDependencyAuditor::NON_POSIX_HOST_FUNCTION_ALLOWLIST);
    }

    public function testConsoleOutputImportsAreAllowed(): void
    {
        (new NativeDependencyAuditor())->assertUndefinedSymbols(
            'wasip2',
            "         U __imported_wasi_snapshot_preview1_fd_write\n"
            . "         U __imported_wasi_snapshot_preview1_proc_exit\n",
        );
        self::addToAssertionCount(1);
    }

    public function testWasip2RejectsSocketAndProcessControlImports(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('proc_raise');
        (new NativeDependencyAuditor())->assertUndefinedSymbols(
            'wasip2',
            "         U __imported_wasi_snapshot_preview1_fd_write\n"
            . "         U __imported_wasi_snapshot_preview1_sock_recv\n"
            . "         U __imported_wasi_snapshot_preview1_proc_raise\n",
        );
    }

    public function testStandardLibraryClockSleepAndEntropyImportsAreAllowed(): void
    {
        $auditor = new NativeDependencyAuditor();
        $auditor->assertUndefinedSymbols(
            'native',
            "                 U clock_gettime@GLIBC_2.17\n"
            . "                 U nanosleep@GLIBC_2.2.5\n",
        );
        $auditor->assertUndefinedSymbols(
            'wasip2',
            "         U __imported_wasi_snapshot_preview1_clock_time_get\n"
            . "         U __imported_wasi_snapshot_preview1_poll_oneoff\n"
            . "         U __imported_wasi_snapshot_preview1_random_get\n",
        );
        self::addToAssertionCount(1);
    }
}
