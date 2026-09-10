<?php

namespace TypePhp\Build;

use RuntimeException;
use TypePhp\Translator;

/**
 * Direct C and C++ source composer for a TypePHP native project.
 *
 * It intentionally does not generate or invoke CMake and does not create
 * intermediate runtime/PHPX libraries.
 */
final class NativeSourceProjectBuilder
{
    private bool $progressLineActive = false;
    private int $progressLineWidth = 0;

    public function __construct(private readonly bool $lineProgress = false)
    {
    }

    /** @return array{output: string, sourceCount: int, compiledCount: int} */
    public function build(NativeSourceProjectConfig $project): array
    {
        if ($project->target === 'native' && PHP_OS_FAMILY === 'Windows') {
            throw new RuntimeException(
                'php-nano does not target Windows; use TypePHP --nano with the full PHP/PHPX DLL runtime'
            );
        }

        $this->writeProgress("Preparing Nano {$project->target} build: {$project->name}");

        $compiler = $this->resolveExecutable($project->compiler);
        $cCompiler = $this->resolveExecutable($project->cCompiler);
        $this->ensureDirectory($project->buildDir);
        $objectDir = $project->buildDir . DIRECTORY_SEPARATOR . 'objects';
        $this->ensureDirectory($objectDir);

        $generatedSources = [];
        $generatedIncludeDir = null;
        if ($project->phpSources !== []) {
            $this->writeProgress(
                'Generating C++ from ' . count($project->phpSources) . ' TypePHP source file(s)'
            );
            $generatedDir = $project->buildDir . DIRECTORY_SEPARATOR . 'generated';
            $translator = Translator::getInstance();
            $phpFiles = $translator->prepareNanoSources(
                $project->phpSources,
                $this->projectSymbolName($project),
                $generatedDir,
                $project->target === 'wasip2',
            );
            $generatedSources = $translator->convert($phpFiles);
            $generatedIncludeDir = $generatedDir . DIRECTORY_SEPARATOR . 'include';
        }

        $composition = (new NanoSourceComposer())->compose(
            $project->buildDir,
            $this->projectSymbolName($project),
            $project->phpSources !== [],
        );
        $packages = $composition['packages'];
        $extensionRegistry = $composition['registry'];
        $sources = [...$project->sources, ...$generatedSources];
        $includeDirs = [];
        if ($generatedIncludeDir !== null) {
            $includeDirs[] = $generatedIncludeDir;
        }
        array_push($sources, ...$composition['packageSources']);
        array_push($includeDirs, ...$composition['includeDirs']);
        $sources[] = $extensionRegistry;
        $sources = array_values(array_unique($sources));
        $includeDirs = array_values(array_unique($includeDirs));
        $sourceCount = count($sources);
        $this->writeProgress(
            "Resolved {$sourceCount} C/C++ source file(s) from " . count($packages) . ' Composer package(s)'
        );
        $strictSources = array_fill_keys(
            [...$project->sources, ...$generatedSources, $extensionRegistry],
            true,
        );

        $objects = [];
        $compiledCount = 0;
        foreach ($sources as $sourceIndex => $source) {
            $isCSource = strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'c';
            $sourceCompiler = $isCSource ? $cCompiler : $compiler;
            $object = $objectDir . DIRECTORY_SEPARATOR
                . pathinfo($source, PATHINFO_FILENAME) . '-' . substr(sha1($source), 0, 12) . '.o';
            $dependencyFile = $object . '.d';
            $signatureFile = $object . '.command';
            $command = [
                $sourceCompiler,
                $isCSource ? '-std=c11' : '-std=c++17',
                ...$this->compileModeFlags($project),
                ...(isset($strictSources[$source]) ? ['-Wall', '-Wextra', '-Wpedantic'] : []),
                '-DTYPEPHP_NATIVE=1',
                '-DPHP_NANO=1',
                '-DPHPX_NANO=1',
                '-D_POSIX_C_SOURCE=200809L',
                '-ffunction-sections',
                '-fdata-sections',
                // Package headers are passed with -isystem to suppress upstream
                // warnings, but they are still build inputs. -MD keeps them in
                // the depfile; -MMD would silently leave stale Nano/PHPX objects.
                '-MD',
                '-MF',
                $dependencyFile,
            ];
            foreach ($includeDirs as $includeDir) {
                $command[] = '-isystem';
                $command[] = $includeDir;
            }
            array_push($command, '-c', $source, '-o', $object);
            $signature = hash('sha256', implode("\0", $command));
            if ($this->needsCompile($object, $dependencyFile, $signatureFile, $signature)) {
                $this->writeCompileProgress(
                    $sourceIndex + 1,
                    $sourceCount,
                    $this->sourceLabel($source, $project, $packages),
                );
                $this->run($command, dirname($project->file));
                $this->writeFile($signatureFile, $signature . "\n");
                ++$compiledCount;
            }
            $objects[] = $object;
        }
        $this->finishProgressLine();
        $cachedCount = $sourceCount - $compiledCount;
        $this->writeProgress("Compilation complete: {$compiledCount} compiled, {$cachedCount} cached");

        $this->ensureDirectory(dirname($project->output));
        $linkCommand = [
            $compiler,
            ...$objects,
            ...$this->linkModeFlags($project),
            '-o',
            $project->output,
        ];
        $auditor = new NativeDependencyAuditor();
        $auditor->assertLinkFlags($project->target, $this->linkModeFlags($project));
        // Link metadata is an intermediate build artifact. Keep it out of the
        // invocation/output directory when the executable defaults to ./name.
        $linkSignatureFile = $project->buildDir . DIRECTORY_SEPARATOR
            . 'link-' . substr(sha1($project->output), 0, 12) . '.command';
        $linkSignature = hash('sha256', implode("\0", $linkCommand));
        if ($compiledCount !== 0
            || $this->needsLink($project->output, $objects, $linkSignatureFile, $linkSignature)) {
            $this->writeProgress('Linking: ' . $project->output);
            $this->run($linkCommand, dirname($project->file));
            $this->writeFile($linkSignatureFile, $linkSignature . "\n");
        } else {
            $this->writeProgress('Link is up to date: ' . $project->output);
        }
        $this->writeProgress('Auditing native dependencies');
        $this->auditArtifact($project, $objects, $compiler, $auditor);

        return [
            'output' => $project->output,
            'sourceCount' => $sourceCount,
            'compiledCount' => $compiledCount,
        ];
    }

    /** @param list<ComposerNativePackage> $packages */
    private function sourceLabel(
        string $source,
        NativeSourceProjectConfig $project,
        array $packages,
    ): string {
        foreach ($packages as $package) {
            $prefix = rtrim($package->installPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($source, $prefix)) {
                return $package->name . '/' . str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    substr($source, strlen($prefix)),
                );
            }
        }
        $buildPrefix = rtrim($project->buildDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($source, $buildPrefix)) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($source, strlen($buildPrefix)));
            return preg_replace('#/+#', '/', $relative) ?? $relative;
        }
        return $source;
    }

    private function writeCompileProgress(int $current, int $total, string $source): void
    {
        $message = "Compiling [{$current}/{$total}] {$source}";
        if ($this->lineProgress || !function_exists('stream_isatty') || !stream_isatty(STDOUT)) {
            $this->writeProgress($message);
            return;
        }

        $padding = max(0, $this->progressLineWidth - strlen($message));
        fwrite(STDOUT, "\r{$message}" . str_repeat(' ', $padding));
        fflush(STDOUT);
        $this->progressLineActive = true;
        $this->progressLineWidth = strlen($message);
    }

    private function writeProgress(string $message): void
    {
        $this->finishProgressLine();
        fwrite(STDOUT, $message . PHP_EOL);
        fflush(STDOUT);
    }

    private function finishProgressLine(): void
    {
        if (!$this->progressLineActive) {
            return;
        }
        fwrite(STDOUT, PHP_EOL);
        fflush(STDOUT);
        $this->progressLineActive = false;
        $this->progressLineWidth = 0;
    }

    private function projectSymbolName(NativeSourceProjectConfig $project): string
    {
        return str_replace(['-', '.'], '_', $project->name);
    }

    private function needsCompile(
        string $object,
        string $dependencyFile,
        string $signatureFile,
        string $signature,
    ): bool {
        if (!is_file($object) || !is_file($dependencyFile)
            || trim((string) @file_get_contents($signatureFile)) !== $signature) {
            return true;
        }

        $objectTime = filemtime($object);
        if ($objectTime === false) {
            return true;
        }
        foreach ($this->readDependencies($dependencyFile) as $dependency) {
            $dependencyTime = filemtime($dependency);
            if ($dependencyTime === false || $dependencyTime > $objectTime) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $objects */
    private function needsLink(
        string $output,
        array $objects,
        string $signatureFile,
        string $signature,
    ): bool {
        if (!is_file($output)
            || trim((string) @file_get_contents($signatureFile)) !== $signature) {
            return true;
        }
        $outputTime = filemtime($output);
        if ($outputTime === false) {
            return true;
        }
        foreach ($objects as $object) {
            $objectTime = filemtime($object);
            if ($objectTime === false || $objectTime > $outputTime) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private function readDependencies(string $dependencyFile): array
    {
        $contents = file_get_contents($dependencyFile);
        if (!is_string($contents)) {
            return [];
        }
        $contents = preg_replace('/\\\\\r?\n/', ' ', $contents) ?? '';
        $separator = strpos($contents, ': ');
        if ($separator === false) {
            return [];
        }
        $dependencies = trim(substr($contents, $separator + 2));
        if ($dependencies === '') {
            return [];
        }
        $entries = preg_split('/(?<!\\\\)\s+/', $dependencies) ?: [];
        return array_values(array_filter(array_map(
            static fn(string $entry): string => str_replace(['\\ ', '\\\\'], [' ', '\\'], $entry),
            $entries,
        ), static fn(string $entry): bool => $entry !== ''));
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write native build metadata: {$path}");
        }
    }

    public function runOutput(NativeSourceProjectConfig $project): never
    {
        $command = $project->target === 'wasip2'
            ? [$this->resolveExecutable('wasmtime'), $project->output]
            : [$project->output];
        $status = $this->run($command, dirname($project->file), false);
        exit($status);
    }

    /** @return list<string> */
    private function compileModeFlags(NativeSourceProjectConfig $project): array
    {
        $flags = $project->buildType === 'debug'
            ? ['-O0', '-g']
            : ['-O2', '-DNDEBUG'];
        if ($project->target === 'wasip2') {
            array_push(
                $flags,
                '-fwasm-exceptions',
                '-DZEND_MM_ERROR=0',
                '-mllvm',
                '-wasm-enable-sjlj',
                '-mllvm',
                '-wasm-use-legacy-eh=false',
            );
        }
        return $flags;
    }

    /** @return list<string> */
    private function linkModeFlags(NativeSourceProjectConfig $project): array
    {
        if ($project->target === 'wasip2') {
            // WASI SDK ships libunwind as the exception ABI companion of its
            // libc++; it is a toolchain runtime, not an application dependency.
            return [
                '-fwasm-exceptions',
                '-lsetjmp',
                '-lunwind',
                '-Wl,--gc-sections',
            ];
        }
        return PHP_OS_FAMILY === 'Darwin'
            ? ['-Wl,-dead_strip']
            : (PHP_OS_FAMILY === 'Windows' ? [] : ['-Wl,--gc-sections']);
    }

    private function resolveExecutable(string $command): string
    {
        if (str_contains($command, '/') || str_contains($command, '\\')) {
            $path = realpath($command);
            if ($path === false || !is_executable($path)) {
                throw new RuntimeException("Native build tool is not executable: {$command}");
            }
            return $path;
        }

        $path = getenv('PATH');
        foreach (explode(PATH_SEPARATOR, is_string($path) ? $path : '') as $directory) {
            if ($directory === '') {
                continue;
            }
            $candidate = $directory . DIRECTORY_SEPARATOR . $command;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        throw new RuntimeException("Native build tool was not found on PATH: {$command}");
    }

    /** @param list<string> $objects */
    private function auditArtifact(
        NativeSourceProjectConfig $project,
        array $objects,
        string $compiler,
        NativeDependencyAuditor $auditor,
    ): void {
        if ($project->target === 'wasip2') {
            $nm = dirname($compiler) . DIRECTORY_SEPARATOR . 'llvm-nm';
            if (!is_executable($nm)) {
                $nm = $this->resolveExecutable('llvm-nm');
            }
            $auditor->assertUndefinedSymbols(
                $project->target,
                $this->runCapture([$nm, '--undefined-only', ...$objects], dirname($project->file)),
            );

            $auditCore = $project->buildDir . DIRECTORY_SEPARATOR . '.native-audit-core.wasm';
            try {
                $this->run([
                    $compiler,
                    ...$objects,
                    ...$this->linkModeFlags($project),
                    '-Wl,--wasi-adapter=none',
                    '-Wl,--skip-wit-component',
                    '-o',
                    $auditCore,
                ], dirname($project->file));
                $auditor->assertUndefinedSymbols(
                    $project->target,
                    $this->runCapture([$nm, '--undefined-only', $auditCore], dirname($project->file)),
                );
            } finally {
                if (is_file($auditCore)) {
                    unlink($auditCore);
                }
            }
            return;
        }

        $nm = $this->resolveExecutable('nm');
        $auditor->assertUndefinedSymbols(
            $project->target,
            $this->runCapture([$nm, '-u', ...$objects], dirname($project->file)),
        );
        $auditor->assertUndefinedSymbols(
            $project->target,
            $this->runCapture(
                [$nm, '-u', $project->output],
                dirname($project->file),
            ),
        );
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create native build directory: {$directory}");
        }
    }

    /** @param list<string> $command */
    private function run(array $command, string $workingDirectory, bool $throw = true): int
    {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $workingDirectory);
        if (!is_resource($process)) {
            throw new RuntimeException("Unable to start native build tool: {$command[0]}");
        }
        $status = proc_close($process);
        if ($throw && $status !== 0) {
            $this->finishProgressLine();
            throw new RuntimeException(
                'Native build command failed with status ' . $status . ': '
                . implode(' ', array_map(escapeshellarg(...), $command))
            );
        }
        return $status;
    }

    /** @param list<string> $command */
    private function runCapture(array $command, string $workingDirectory): string
    {
        $process = proc_open(
            $command,
            [STDIN, ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $workingDirectory,
        );
        if (!is_resource($process)) {
            throw new RuntimeException("Unable to start native audit tool: {$command[0]}");
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            throw new RuntimeException(
                'Native audit command failed with status ' . $status . ': '
                . implode(' ', array_map(escapeshellarg(...), $command))
                . ($stderr === '' ? '' : "\n{$stderr}")
            );
        }
        return (string) $stdout;
    }
}
