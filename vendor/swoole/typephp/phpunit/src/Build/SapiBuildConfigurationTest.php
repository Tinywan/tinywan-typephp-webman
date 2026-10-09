<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\SapiBuildConfiguration;

final class SapiBuildConfigurationTest extends TestCase
{
    public function testTargetsAcceptEmbedCliFpmAndLists(): void
    {
        self::assertSame(['embed'], SapiBuildConfiguration::parseTargets('embed'));
        self::assertSame(['cli'], SapiBuildConfiguration::parseTargets('cli'));
        self::assertSame(['fpm', 'cli'], SapiBuildConfiguration::parseTargets(['fpm', 'cli', 'fpm']));
        self::assertSame(['embed', 'cli', 'fpm'], SapiBuildConfiguration::parseTargets('embed, cli, fpm'));
    }

    public function testInvalidTargetIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected embed, cli, or fpm');
        SapiBuildConfiguration::parseTargets('apache');
    }
}
