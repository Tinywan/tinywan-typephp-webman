<?php

namespace TypePhp\Build;

use Closure;
use TypePhp\Backend\CompilerBackend;

final readonly class NativeBuilder
{
    public function __construct(private CompilerBackend $backend)
    {
    }

    public function compileCommand(string $source, string $object, CompileOptions $options, ?string $language): string
    {
        if ($language === null) {
            return $this->backend->buildCompileCommand($source, $object, $options->toArray());
        }
        if ($language === 'c') {
            return $this->backend->buildCCompileCommand($source, $object, $options->toArray());
        }
        return $this->backend->buildNativeCompileCommand($source, $object, $options->toArray(), $language);
    }

    public function linkCommand(array $objects, string $target, LinkOptions $options): string
    {
        return $this->backend->buildLinkCommand($objects, $target, $options->toArray());
    }

    /** @return array{command: string, output: list<string>, status: int} */
    public function compile(string $source, string $object, CompileOptions $options, ?string $language, bool $quiet): array
    {
        $command = $this->compileCommand($source, $object, $options, $language);
        $output = [];
        if ($quiet) {
            exec($command . ' 2>&1', $output, $status);
        } else {
            passthru($command, $status);
        }
        return ['command' => $command, 'output' => $output, 'status' => $status];
    }

    /** @return array{command: string, output: list<string>, status: int, generated: bool} */
    public function link(array $objects, string $target, LinkOptions $options): array
    {
        $command = $this->linkCommand($objects, $target, $options);
        try {
            exec($command . ' 2>&1', $output, $status);
            return [
                'command' => $command,
                'output' => $output,
                'status' => $status,
                'generated' => file_exists($target),
            ];
        } finally {
            $this->cleanup();
        }
    }

    /**
     * @param Closure(string): string $objectFile
     * @param Closure(string, string): void $worker
     * @param Closure(): int $fork
     * @param Closure(): array{int, int} $wait
     * @param Closure(int): bool $succeeded
     * @param null|Closure(string, string, int, bool, int): void $completed
     * @return array{objects: list<string>, failures: list<string>}
     */
    public function dispatchParallel(
        array $sources,
        int $jobs,
        Closure $objectFile,
        Closure $worker,
        Closure $fork,
        Closure $wait,
        Closure $succeeded,
        ?Closure $completed = null,
    ): array {
        // Keep most workers on the largest translation units to reduce the
        // parallel tail, but reserve one fast lane for small files so progress
        // remains visible while the expensive units are still compiling.
        $queue = SourceCompileQueue::largestFirst($sources);
        $running = [];
        $objects = [];
        $failures = [];
        $completedCount = 0;
        $largeTaskCount = 0;
        $largeLaneLimit = max(1, $jobs - 1);

        while ($queue !== [] || $running !== []) {
            while (count($running) < $jobs && $queue !== []) {
                if ($largeTaskCount < $largeLaneLimit) {
                    $source = array_shift($queue);
                    $lane = 'large';
                } else {
                    $source = array_pop($queue);
                    $lane = 'small';
                }
                $object = $objectFile($source);
                $pid = $fork();
                if ($pid === -1) {
                    $failures[] = $source;
                    array_push($failures, ...$queue);
                    $queue = [];
                    break;
                }
                if ($pid === 0) {
                    try {
                        $worker($source, $object);
                        exit(is_file($object) ? 0 : 1);
                    } catch (\Throwable) {
                        exit(1);
                    }
                }
                $running[$pid] = ['source' => $source, 'object' => $object, 'lane' => $lane];
                if ($lane === 'large') {
                    $largeTaskCount++;
                }
            }
            if ($running === []) {
                break;
            }
            [$pid, $status] = $wait();
            $task = $running[$pid] ?? null;
            unset($running[$pid]);
            if ($task === null) {
                continue;
            }
            if ($task['lane'] === 'large') {
                $largeTaskCount--;
            }
            $success = $succeeded($status);
            if ($success) {
                $objects[] = $task['object'];
            } else {
                $failures[] = $task['source'];
            }
            $completedCount++;
            $completed?->__invoke($task['source'], $task['object'], $status, $success, $completedCount);
        }
        return ['objects' => $objects, 'failures' => $failures];
    }

    public function cleanup(): void
    {
        $this->backend->cleanupResponseFile();
    }
}
