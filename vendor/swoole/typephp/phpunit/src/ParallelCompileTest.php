<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Backend\CompilerBackend;
use TypePhp\Build\NativeBuilder;

final class ParallelCompileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp_process_pool_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testProcessPoolRunsCompilerTasksConcurrently(): void
    {
        $firstMarker = $this->directory . '/first.ready';
        $secondMarker = $this->directory . '/second.ready';
        $firstObject = $this->directory . '/first.o';
        $secondObject = $this->directory . '/second.o';
        $completed = [];

        $builder = new NativeBuilder($this->createMock(CompilerBackend::class));
        $result = $builder->dispatchProcessParallel(
            [
                $this->task('first.cc', $firstObject, $this->barrierCommand($firstMarker, $secondMarker, $firstObject)),
                $this->task('second.cc', $secondObject, $this->barrierCommand($secondMarker, $firstMarker, $secondObject)),
            ],
            2,
            static function (
                string $source,
                string $object,
                string $command,
                array $output,
                int $status,
                bool $success,
                int $count,
            ) use (&$completed): void {
                $completed[$source] = [$object, $command, $output, $status, $success, $count];
            },
        );

        self::assertSame([], $result['failures'], var_export($completed, true));
        self::assertEqualsCanonicalizing([$firstObject, $secondObject], $result['objects']);
        self::assertCount(2, $completed);
        self::assertTrue($completed['first.cc'][4]);
        self::assertTrue($completed['second.cc'][4]);
    }

    public function testProcessPoolCapturesCompilerFailureOutputAndStatus(): void
    {
        $object = $this->directory . '/failed.o';
        $completion = null;
        $command = $this->phpCommand("fwrite(STDERR, 'compiler-error'); exit(7);");

        $builder = new NativeBuilder($this->createMock(CompilerBackend::class));
        $result = $builder->dispatchProcessParallel(
            [$this->task('failed.cc', $object, $command)],
            4,
            static function (
                string $source,
                string $object,
                string $command,
                array $output,
                int $status,
                bool $success,
                int $count,
            ) use (&$completion): void {
                $completion = compact('source', 'object', 'command', 'output', 'status', 'success', 'count');
            },
        );

        self::assertSame([], $result['objects']);
        self::assertSame(['failed.cc'], $result['failures']);
        self::assertSame(7, $completion['status']);
        self::assertFalse($completion['success']);
        self::assertStringContainsString('compiler-error', implode("\n", $completion['output']));
    }

    public function testProcessPoolCanReserveOneLaneForSmallTasks(): void
    {
        $smallMarker = $this->directory . '/small.ready';
        $largeObject = $this->directory . '/large.o';
        $middleObject = $this->directory . '/middle.o';
        $smallObject = $this->directory . '/small.o';

        $builder = new NativeBuilder($this->createMock(CompilerBackend::class));
        $result = $builder->dispatchProcessParallel(
            [
                $this->task(
                    'large.cc',
                    $largeObject,
                    $this->waitForFileCommand($smallMarker, $largeObject),
                ),
                $this->task(
                    'middle.cc',
                    $middleObject,
                    $this->waitForFileCommand($largeObject, $middleObject),
                ),
                $this->task(
                    'small.cc',
                    $smallObject,
                    $this->createFilesCommand([$smallMarker, $smallObject]),
                ),
            ],
            2,
            reserveSmallTaskLane: true,
        );

        self::assertSame([], $result['failures']);
        self::assertEqualsCanonicalizing(
            [$largeObject, $middleObject, $smallObject],
            $result['objects'],
        );
    }

    /** @return array{source: string, object: string, command: string} */
    private function task(string $source, string $object, string $command): array
    {
        return compact('source', 'object', 'command');
    }

    private function barrierCommand(string $ownMarker, string $otherMarker, string $object): string
    {
        $code = 'file_put_contents(' . var_export($ownMarker, true) . ", 'ready');"
            . '$deadline = microtime(true) + 5;'
            . 'while (file_exists(' . var_export($otherMarker, true) . ') === false && microtime(true) < $deadline) {'
            . ' usleep(10000); }'
            . 'if (file_exists(' . var_export($otherMarker, true) . ') === false) { exit(9); }'
            . 'file_put_contents(' . var_export($object, true) . ", 'object');";
        return $this->phpCommand($code);
    }

    /** @param list<string> $files */
    private function createFilesCommand(array $files): string
    {
        $code = '';
        foreach ($files as $file) {
            $code .= 'file_put_contents(' . var_export($file, true) . ", 'ready');";
        }
        return $this->phpCommand($code);
    }

    private function waitForFileCommand(string $dependency, string $object): string
    {
        $code = '$deadline = microtime(true) + 2;'
            . 'while (file_exists(' . var_export($dependency, true) . ') === false && microtime(true) < $deadline) {'
            . ' usleep(10000); }'
            . 'if (file_exists(' . var_export($dependency, true) . ') === false) { exit(9); }'
            . 'file_put_contents(' . var_export($object, true) . ", 'object');";
        return $this->phpCommand($code);
    }

    private function phpCommand(string $code): string
    {
        return escapeshellarg(PHP_BINARY) . ' -n -r ' . escapeshellarg($code);
    }
}
