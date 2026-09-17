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
     * Run compiler commands concurrently through proc_open(). Keeping the
     * process pool independent of pcntl makes parallel builds available in a
     * stock PHP installation on Linux, macOS, and Windows.
     *
     * Output is redirected to one temporary file per process. This avoids the
     * pipe-buffer deadlocks that can otherwise occur when a compiler emits a
     * large diagnostic while the parent is waiting for another process.
     *
     * @param list<array{source: string, object: string, command: string}> $tasks
     * @param null|Closure(string, string, string, list<string>, int, bool, int): void $completed
     * @param bool $reserveSmallTaskLane Whether the size-sorted queue reserves
     *        one worker for tasks taken from its small-file end.
     * @return array{objects: list<string>, failures: list<string>}
     */
    public function dispatchProcessParallel(
        array $tasks,
        int $jobs,
        ?Closure $completed = null,
        bool $reserveSmallTaskLane = false,
    ): array {
        $jobs = max(1, $jobs);
        $queue = $tasks;
        $running = [];
        $objects = [];
        $failures = [];
        $completedCount = 0;
        $largeTaskCount = 0;
        $largeLaneLimit = $reserveSmallTaskLane ? max(1, $jobs - 1) : $jobs;
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        try {
            while ($queue !== [] || $running !== []) {
                while (count($running) < $jobs && $queue !== []) {
                    // The caller supplies tasks largest-first. Keep most workers
                    // on that end to minimize the parallel tail, while one fast
                    // lane drains the smallest end and keeps progress observable
                    // during very expensive translation units.
                    if ($reserveSmallTaskLane && $largeTaskCount >= $largeLaneLimit) {
                        $task = array_pop($queue);
                        $lane = 'small';
                    } else {
                        $task = array_shift($queue);
                        $lane = 'large';
                    }
                    $logFile = tempnam(sys_get_temp_dir(), 'typephp-compile-');
                    if ($logFile === false) {
                        $failures[] = $task['source'];
                        $completedCount++;
                        $completed?->__invoke(
                            $task['source'],
                            $task['object'],
                            $task['command'],
                            ['Unable to create compiler output file'],
                            1,
                            false,
                            $completedCount,
                        );
                        continue;
                    }

                    $process = @proc_open(
                        $task['command'],
                        [
                            0 => ['file', $nullDevice, 'r'],
                            1 => ['file', $logFile, 'a'],
                            2 => ['file', $logFile, 'a'],
                        ],
                        $pipes,
                    );
                    if (!is_resource($process)) {
                        @unlink($logFile);
                        $failures[] = $task['source'];
                        $completedCount++;
                        $completed?->__invoke(
                            $task['source'],
                            $task['object'],
                            $task['command'],
                            ['Unable to start compiler process'],
                            1,
                            false,
                            $completedCount,
                        );
                        continue;
                    }

                    $running[] = [
                        'task' => $task,
                        'process' => $process,
                        'log' => $logFile,
                        'lane' => $lane,
                    ];
                    if ($lane === 'large') {
                        $largeTaskCount++;
                    }
                }

                if ($running === []) {
                    continue;
                }

                $finished = false;
                foreach ($running as $index => $entry) {
                    $status = proc_get_status($entry['process']);
                    if ($status['running']) {
                        continue;
                    }

                    $finished = true;
                    $exitCode = (int) $status['exitcode'];
                    $closeCode = proc_close($entry['process']);
                    if ($exitCode < 0 && $closeCode >= 0) {
                        $exitCode = $closeCode;
                    }

                    $contents = file_get_contents($entry['log']);
                    @unlink($entry['log']);
                    $output = $contents === false || $contents === ''
                        ? []
                        : (preg_split('/\R/', rtrim($contents)) ?: []);
                    $task = $entry['task'];
                    if ($entry['lane'] === 'large') {
                        $largeTaskCount--;
                    }
                    $success = $exitCode === 0 && is_file($task['object']);
                    if ($success) {
                        $objects[] = $task['object'];
                    } else {
                        $failures[] = $task['source'];
                    }
                    $completedCount++;
                    unset($running[$index]);
                    $completed?->__invoke(
                        $task['source'],
                        $task['object'],
                        $task['command'],
                        $output,
                        $exitCode,
                        $success,
                        $completedCount,
                    );
                }
                $running = array_values($running);

                if (!$finished) {
                    usleep(10_000);
                }
            }
        } finally {
            foreach ($running as $entry) {
                if (is_resource($entry['process'])) {
                    proc_terminate($entry['process']);
                    proc_close($entry['process']);
                }
                @unlink($entry['log']);
            }
        }

        return ['objects' => $objects, 'failures' => $failures];
    }

    public function cleanup(): void
    {
        $this->backend->cleanupResponseFile();
    }
}
