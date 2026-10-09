<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\NanoBuildBackend;

final class NanoBuildBackendTest extends TestCase
{
    /** @dataProvider nativeHosts */
    public function testEveryNativeHostComposesComposerRuntimeSources(string $osFamily): void
    {
        self::assertSame(NanoBuildBackend::COMPOSER_SOURCES, NanoBuildBackend::forHost($osFamily));
        self::assertTrue(NanoBuildBackend::composesRuntimeSources($osFamily));
    }

    public static function nativeHosts(): array
    {
        return [
            ['Windows'],
            ['Linux'],
            ['Darwin'],
            ['BSD'],
        ];
    }
}
