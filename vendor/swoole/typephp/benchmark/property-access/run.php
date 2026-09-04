<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = __DIR__ . '/benchmark.php';
$project = __DIR__ . '/project.yml';
$binary = __DIR__ . '/property_access';
$skipBuild = in_array('--skip-build', $argv, true);
$maximumRatio = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--max-ratio=')) {
        $maximumRatio = (float) substr($argument, strlen('--max-ratio='));
    }
}

/**
 * @param list<string> $command
 * @param array<string, string>|null $environment
 */
function runCommand(array $command, string $cwd, bool $capture, ?array $environment = null): string
{
    $stdout = $capture ? ['pipe', 'w'] : STDOUT;
    $stderr = $capture ? ['pipe', 'w'] : STDERR;
    $process = proc_open(
        $command,
        [STDIN, $stdout, $stderr],
        $pipes,
        $cwd,
        $environment,
        ['bypass_shell' => true],
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start: ' . implode(' ', $command));
    }

    $output = '';
    $error = '';
    if ($capture) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
    }
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException(
            'Command failed (' . $status . '): ' . implode(' ', $command) . "\n" . $output . $error,
        );
    }
    return $output;
}

/** @return array<string, float> */
function parseResults(string $output): array
{
    $results = [];
    foreach (explode("\n", trim($output)) as $line) {
        if (!str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        if ($name !== 'checksum') {
            $results[$name] = (float) $value;
        }
    }
    return $results;
}

if (!$skipBuild) {
    runCommand([
        PHP_BINARY,
        $root . '/bin/tpc.php',
        $project,
        '-j',
        '8',
        '--no-color',
        '--no-progress',
    ], $root, false);
}
if (!is_file($binary)) {
    throw new RuntimeException('Benchmark binary does not exist: ' . $binary);
}

$php = parseResults(runCommand([
    PHP_BINARY,
    '-d',
    'opcache.enable_cli=0',
    '-r',
    'require ' . var_export($source, true) . '; main();',
], $root, true));
$typephpEnvironment = null;
if (PHP_OS_FAMILY !== 'Windows') {
    $phpxHome = getenv('PHPX_HOME');
    if (!is_string($phpxHome) || $phpxHome === '') {
        $phpxHome = $root . '/vendor/swoole/phpx';
    }
    $typephpEnvironment = getenv();
    $loaderVariable = PHP_OS_FAMILY === 'Darwin' ? 'DYLD_LIBRARY_PATH' : 'LD_LIBRARY_PATH';
    $existingPath = $typephpEnvironment[$loaderVariable] ?? '';
    $typephpEnvironment[$loaderVariable] = $phpxHome . '/lib'
        . ($existingPath === '' ? '' : PATH_SEPARATOR . $existingPath);
}
$typephp = parseResults(runCommand([$binary], $root, true, $typephpEnvironment));

echo "Metric                  PHP ns/op  TypePHP ns/op  TypePHP/PHP\n";
echo "------------------------------------------------------------\n";
$failed = false;
foreach (['dynamic_write_ns', 'dynamic_read_ns', 'static_write_ns', 'static_read_ns'] as $metric) {
    if (!isset($php[$metric], $typephp[$metric])) {
        throw new RuntimeException('Missing benchmark metric: ' . $metric);
    }
    $ratio = $typephp[$metric] / $php[$metric];
    printf("%-22s %10.2f %14.2f %12.2fx\n", $metric, $php[$metric], $typephp[$metric], $ratio);
    if ($maximumRatio !== null && str_starts_with($metric, 'dynamic_') && $ratio > $maximumRatio) {
        $failed = true;
    }
}

if ($failed) {
    fwrite(STDERR, "Dynamic property ratio exceeded --max-ratio={$maximumRatio}\n");
    exit(1);
}
