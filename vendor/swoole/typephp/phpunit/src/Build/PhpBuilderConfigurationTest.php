<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\PhpBuilderConfiguration;

/**
 * @internal
 * @coversNothing
 */
final class PhpBuilderConfigurationTest extends TestCase
{
    public function testParsesYamlMapping(): void
    {
        $configuration = PhpBuilderConfiguration::fromYaml([
            'zts' => 'on',
            'extensions' => ['swoole', 'ext-mongodb', 'swoole'],
        ]);

        self::assertTrue($configuration->zts);
        self::assertSame(['mongodb', 'swoole'], $configuration->extensions);
    }

    public function testParsesCommandLineSyntax(): void
    {
        $configuration = PhpBuilderConfiguration::fromCommandLine(
            'extensions: [curl, mbstring]; zts: off;',
        );

        self::assertFalse($configuration->zts);
        self::assertSame(['curl', 'mbstring'], $configuration->extensions);
    }

    public function testEmptyCommandLineConfigurationUsesMappingDefaults(): void
    {
        $configuration = PhpBuilderConfiguration::fromCommandLine('');

        self::assertFalse($configuration->zts);
        self::assertSame([], $configuration->extensions);
    }

    public function testRejectsLegacyListOfMappingsYamlShape(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('`php-builder` must be a mapping');

        PhpBuilderConfiguration::fromYaml([
            ['zts' => true],
            ['extensions' => []],
        ]);
    }

    public function testRejectsUnknownOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown `php-builder` option');

        PhpBuilderConfiguration::fromYaml(['sapi' => ['embed']]);
    }
}
