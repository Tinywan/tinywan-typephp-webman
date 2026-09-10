<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\NanoBuildBackend;

final class NanoBuildBackendTest extends TestCase
{
    public function testWindowsKeepsTheHostDllBuildBackend(): void
    {
        self::assertSame(NanoBuildBackend::WINDOWS_DLL, NanoBuildBackend::forHost('Windows'));
        self::assertFalse(NanoBuildBackend::composesRuntimeSources('Windows'));
    }

    /** @dataProvider nonWindowsHosts */
    public function testNonWindowsHostsComposeComposerRuntimeSources(string $osFamily): void
    {
        self::assertSame(NanoBuildBackend::COMPOSER_SOURCES, NanoBuildBackend::forHost($osFamily));
        self::assertTrue(NanoBuildBackend::composesRuntimeSources($osFamily));
    }

    public static function nonWindowsHosts(): array
    {
        return [
            ['Linux'],
            ['Darwin'],
            ['BSD'],
        ];
    }
}
