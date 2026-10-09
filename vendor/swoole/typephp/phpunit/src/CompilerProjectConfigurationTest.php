<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Config\ProjectYamlLoader;

final class CompilerProjectConfigurationTest extends TestCase
{
    public function testReleaseCompilerUsesProductionSettings(): void
    {
        $root = dirname(__DIR__, 2);
        $loader = new ProjectYamlLoader(
            PHP_VERSION,
            static fn(string $message): never => throw new \RuntimeException($message),
        );
        $development = $loader->load($root . '/project.yml');
        $release = $loader->load($root . '/project-release.yml');

        $this->assertArrayNotHasKey('embedded-files', $development);
        $this->assertArrayNotHasKey('optimize', $development);
        $this->assertSame(['./vendor'], $release['embedded-files'] ?? null);
        $this->assertSame(2, $release['optimize'] ?? null);

        unset($release['embedded-files'], $release['optimize']);
        $this->assertSame($development, $release);
    }
}
