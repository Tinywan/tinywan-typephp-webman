<?php

namespace TypePhp\Tests\Config;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Config\ProjectYamlLoader;

final class ProjectYamlLoaderTest extends TestCase
{
    private string $directory;
    private ProjectYamlLoader $loader;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-project-yaml-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
        $this->loader = new ProjectYamlLoader(
            '8.4.14',
            static fn(string $message): never => throw new RuntimeException($message),
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.yml') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testIncludedConfigurationIsMergedBeforeRootConfiguration(): void
    {
        file_put_contents($this->directory . '/common.yml', <<<'YAML'
name: common
sources:
  - src
defines:
  - COMMON=1
resource:
  version-info:
    company-name: Common Team
    product-name: Common Product
YAML);
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
include: common.yml
name: app
defines:
  - APP=1
resource:
  version-info:
    company-name: Application Team
YAML);

        self::assertSame([
            'name' => 'app',
            'sources' => ['src'],
            'defines' => ['APP=1'],
            'resource' => [
                'version-info' => [
                    'company-name' => 'Application Team',
                    'product-name' => 'Common Product',
                ],
            ],
        ], $this->loader->load($this->directory . '/project.yml'));
    }

    public function testMultipleIncludesAreAppliedInOrder(): void
    {
        file_put_contents($this->directory . '/first.yml', "name: first\noptimize: 1\n");
        file_put_contents($this->directory . '/second.yml', "name: second\njob: 4\n");
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
include:
  - first.yml
  - second.yml
name: project
YAML);

        self::assertSame([
            'name' => 'project',
            'optimize' => 1,
            'job' => 4,
        ], $this->loader->load($this->directory . '/project.yml'));
    }

    public function testNestedIncludesAreSupported(): void
    {
        file_put_contents($this->directory . '/grandparent.yml', "optimize: 2\n");
        file_put_contents($this->directory . '/parent.yml', "include: grandparent.yml\njob: 4\n");
        file_put_contents($this->directory . '/project.yml', "include: parent.yml\nname: app\n");

        self::assertSame([
            'optimize' => 2,
            'job' => 4,
            'name' => 'app',
        ], $this->loader->load($this->directory . '/project.yml'));
    }

    public function testCompletedIncludeCanBeLoadedAgain(): void
    {
        file_put_contents($this->directory . '/shared.yml', "optimize: 2\n");
        file_put_contents($this->directory . '/first.yml', "include: shared.yml\njob: 2\n");
        file_put_contents($this->directory . '/second.yml', "include: shared.yml\njob: 4\n");
        file_put_contents($this->directory . '/project.yml', <<<'YAML'
include:
  - first.yml
  - second.yml
YAML);

        self::assertSame([
            'optimize' => 2,
            'job' => 4,
        ], $this->loader->load($this->directory . '/project.yml'));
    }

    public function testCircularIncludeIsRejectedWhileFileIsBeingParsed(): void
    {
        file_put_contents($this->directory . '/parent.yml', "include: project.yml\n");
        file_put_contents($this->directory . '/project.yml', "include: parent.yml\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular project YAML include');
        $this->loader->load($this->directory . '/project.yml');
    }
}
