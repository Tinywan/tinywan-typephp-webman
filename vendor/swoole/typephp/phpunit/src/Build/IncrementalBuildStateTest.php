<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\IncrementalBuildState;

final class IncrementalBuildStateTest extends TestCase
{
    private string $directory;
    private string $stateFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp_build_graph_' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0777, true);
        $this->stateFile = $this->directory . '/build-state.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->stateFile)) {
            unlink($this->stateFile);
        }
        rmdir($this->directory);
    }

    public function testChangedDependencyPropagatesThroughCyclesAndConsumers(): void
    {
        $this->saveBaseline();
        $state = new IncrementalBuildState($this->stateFile);

        $dirty = $state->dirtyFiles(
            ['A.php' => 'a', 'B.php' => 'b2', 'C.php' => 'c', 'D.php' => 'd'],
            [
                'A.php' => ['B.php'],
                'B.php' => ['A.php'],
                'C.php' => ['A.php'],
                'D.php' => [],
            ],
            'generator',
            static fn(string $_source, ?array $_metadata): bool => true,
        );

        self::assertSame(['A.php', 'B.php', 'C.php'], array_keys($dirty));
    }

    public function testRemovedDependencyInvalidatesItsPreviousConsumers(): void
    {
        $this->saveBaseline();
        $state = new IncrementalBuildState($this->stateFile);

        $dirty = $state->dirtyFiles(
            ['B.php' => 'b', 'C.php' => 'c', 'D.php' => 'd'],
            ['B.php' => [], 'C.php' => [], 'D.php' => []],
            'generator',
            static fn(string $_source, ?array $_metadata): bool => true,
        );

        self::assertSame(['B.php', 'C.php'], array_keys($dirty));
    }

    public function testMissingArtifactAndGeneratorChangeInvalidateExpectedFiles(): void
    {
        $this->saveBaseline();
        $state = new IncrementalBuildState($this->stateFile);
        $hashes = ['A.php' => 'a', 'B.php' => 'b', 'C.php' => 'c', 'D.php' => 'd'];
        $dependencies = [
            'A.php' => ['B.php'],
            'B.php' => ['A.php'],
            'C.php' => ['A.php'],
            'D.php' => [],
        ];

        $dirty = $state->dirtyFiles(
            $hashes,
            $dependencies,
            'generator',
            static fn(string $source, ?array $_metadata): bool => $source !== 'A.php',
        );
        self::assertSame(['A.php', 'B.php', 'C.php'], array_keys($dirty));

        $dirty = $state->dirtyFiles(
            $hashes,
            $dependencies,
            'new-generator',
            static fn(string $_source, ?array $_metadata): bool => true,
        );
        self::assertSame(array_keys($hashes), array_keys($dirty));
    }

    private function saveBaseline(): void
    {
        $state = new IncrementalBuildState($this->stateFile);
        $state->save('generator', [
            'A.php' => $this->metadata('a', ['B.php']),
            'B.php' => $this->metadata('b', ['A.php']),
            'C.php' => $this->metadata('c', ['A.php']),
            'D.php' => $this->metadata('d', []),
        ]);
    }

    /** @param list<string> $dependencies @return array<string, mixed> */
    private function metadata(string $hash, array $dependencies): array
    {
        return [
            'hash' => $hash,
            'dependencies' => $dependencies,
            'emitsTranslationUnit' => true,
        ];
    }
}
