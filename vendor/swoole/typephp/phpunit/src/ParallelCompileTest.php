<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Backend\CompilerBackend;
use TypePhp\Build\NativeBuilder;
use TypePhp\CompilerTest;

class ParallelCompileTest extends TestCase
{
    public function testWaitRetriesWhenInterrupted(): void
    {
        $compiler = new ScriptedWaitCompiler([
            [-1, 0, PCNTL_EINTR],
            [123, 0, 0],
        ]);

        $this->assertSame([123, 0], $compiler->waitForTest());
        $this->assertSame(2, $compiler->getWaitCallCount());
    }

    public function testWaitFailureOtherThanInterruptionThrows(): void
    {
        $compiler = new ScriptedWaitCompiler([
            [-1, 0, PCNTL_ECHILD],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to wait for compiler process');
        $compiler->waitForTest();
    }

    public function testSignaledChildIsNotSuccessful(): void
    {
        $compiler = new ScriptedWaitCompiler([]);

        $this->assertTrue($compiler->statusSucceeded(0));
        $this->assertFalse($compiler->statusSucceeded(1 << 8));
        $this->assertFalse($compiler->statusSucceeded(SIGTERM));
    }

    public function testForkFailureStillReapsRunningCompilerProcesses(): void
    {
        $compiler = new ScriptedWaitCompiler(
            [[101, 0, 0]],
            [101, -1]
        );

        try {
            $compiler->compileInParallelForTest(['first.cc', 'second.cc', 'third.cc'], 2);
            $this->fail('The fork failure should fail the parallel compilation');
        } catch (\Exception $e) {
            $this->assertStringContainsString('second.cc', $e->getMessage());
            $this->assertStringContainsString('third.cc', $e->getMessage());
        }

        $this->assertSame(1, $compiler->getWaitCallCount());
    }

    public function testParallelDispatcherReportsEachCompletedTask(): void
    {
        $builder = new NativeBuilder($this->createMock(CompilerBackend::class));
        $forkResults = [101, 102];
        $waitResults = [[102, 0], [101, 1 << 8]];
        $completed = [];

        $result = $builder->dispatchParallel(
            ['first.cc', 'second.cc'],
            2,
            static fn(string $source): string => $source . '.o',
            static function (): void {},
            static function () use (&$forkResults): int {
                return array_shift($forkResults);
            },
            static function () use (&$waitResults): array {
                return array_shift($waitResults);
            },
            static fn(int $status): bool => $status === 0,
            static function (string $source, string $object, int $status, bool $success, int $count) use (&$completed): void {
                $completed[] = [$source, $object, $status, $success, $count];
            },
        );

        $this->assertSame(['second.cc.o'], $result['objects']);
        $this->assertSame(['first.cc'], $result['failures']);
        $this->assertSame([
            ['second.cc', 'second.cc.o', 0, true, 1],
            ['first.cc', 'first.cc.o', 1 << 8, false, 2],
        ], $completed);
    }
}

class ScriptedWaitCompiler extends CompilerTest
{
    private int $waitCallCount = 0;
    private int $lastWaitError = 0;

    public function __construct(private array $waitResults, private array $forkResults = [])
    {
        parent::__construct(TYPEPHP_ROOT_PATH);
        $this->noProgress = true;
    }

    protected function pcntlFork(): int
    {
        return array_shift($this->forkResults);
    }

    protected function pcntlWait(?int &$status): int
    {
        $this->waitCallCount++;
        [$pid, $status, $this->lastWaitError] = array_shift($this->waitResults);
        return $pid;
    }

    protected function pcntlLastError(): int
    {
        return $this->lastWaitError;
    }

    public function waitForTest(): array
    {
        return $this->waitForCompileChild();
    }

    public function statusSucceeded(int $status): bool
    {
        return $this->compileChildSucceeded($status);
    }

    public function getWaitCallCount(): int
    {
        return $this->waitCallCount;
    }

    public function compileInParallelForTest(array $sourceFiles, int $jobs): array
    {
        return $this->compileWithPcntl($sourceFiles, $jobs);
    }
}
