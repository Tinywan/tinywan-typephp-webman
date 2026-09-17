<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\StableIdRegistry;

final class StableIdRegistryTest extends TestCase
{
    private string $directory;
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp_stable_ids_' . bin2hex(random_bytes(8));
        $this->cacheFile = $this->directory . '/stable-ids.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testIdsRemainStableAcrossCompilerProcesses(): void
    {
        $first = new StableIdRegistry($this->cacheFile);
        self::assertSame(0, $first->allocate('literal', "first\0value"));
        self::assertSame(1, $first->allocate('literal', 'second'));
        self::assertSame(0, $first->allocate('class', 'Example'));
        $first->flush();

        $second = new StableIdRegistry($this->cacheFile);
        self::assertSame(1, $second->allocate('literal', 'second'));
        self::assertSame(0, $second->allocate('literal', "first\0value"));
        self::assertSame(2, $second->allocate('literal', 'third'));
        self::assertSame(3, $second->capacity('literal'));
        self::assertSame([
            "first\0value" => 0,
            'second' => 1,
            'third' => 2,
        ], $second->entries('literal'));
    }

    public function testInvalidPersistedCursorCannotReuseAnExistingId(): void
    {
        mkdir($this->directory, 0777, true);
        file_put_contents($this->cacheFile, json_encode([
            'schema' => 2,
            'domains' => [
                'function' => ['b:' . base64_encode('old') => 7],
            ],
            'nextIds' => ['function' => 1],
        ], JSON_THROW_ON_ERROR));

        $registry = new StableIdRegistry($this->cacheFile);

        self::assertSame(7, $registry->allocate('function', 'old'));
        self::assertSame(8, $registry->allocate('function', 'new'));
    }
}
