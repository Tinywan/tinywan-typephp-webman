<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = __DIR__ . '/benchmark.php';
$project = __DIR__ . '/project.yml';
$binary = __DIR__ . '/bridge_benchmark' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
$skipBuild = in_array('--skip-build', $argv, true);
$selectedCase = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--case=')) {
        $selectedCase = substr($argument, strlen('--case='));
    }
}

/** @param list<string> $command */
function runBridgeCommand(array $command, string $cwd, ?array $environment = null): string
{
    $process = proc_open(
        $command,
        [STDIN, ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
        $cwd,
        $environment,
        ['bypass_shell' => true],
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start: ' . implode(' ', $command));
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException(
            'Command failed (' . $status . '): ' . implode(' ', $command) . "\n" . $output . $error,
        );
    }
    return $output;
}

/** @return array<string, float> */
function parseBridgeResults(string $output): array
{
    $results = [];
    foreach (explode("\n", trim($output)) as $line) {
        if (!preg_match('/^([a-z_]+)_ns=([0-9.]+)$/', $line, $matches)) {
            continue;
        }
        $results[$matches[1]] = (float) $matches[2];
    }
    return $results;
}

$compilerPhp = getenv('TPC_PHP_BIN') ?: PHP_BINARY;
$baselinePhp = getenv('PHP_BIN') ?: PHP_BINARY;
if (!$skipBuild) {
    echo "Building TypePHP benchmark (-O3 + LTO)...\n";
    echo runBridgeCommand([
        $compilerPhp,
        $root . '/bin/tpc.php',
        $project,
        '-j',
        '8',
        '--no-color',
        '--no-progress',
    ], $root);
}
if (!is_file($binary)) {
    throw new RuntimeException('Benchmark binary does not exist: ' . $binary);
}

$benchmarkEnvironment = getenv();
if ($selectedCase !== null && $selectedCase !== '') {
    $benchmarkEnvironment['BRIDGE_CASE'] = $selectedCase;
}
$php = parseBridgeResults(runBridgeCommand([
    $baselinePhp,
    '-n',
    '-d',
    'opcache.enable_cli=0',
    '-d',
    'opcache.jit=0',
    '-r',
    'require ' . var_export($source, true) . '; main();',
], $root, $benchmarkEnvironment));

$environment = $benchmarkEnvironment;
if (PHP_OS_FAMILY !== 'Windows') {
    $phpxHome = getenv('PHPX_HOME') ?: dirname($root) . '/phpx';
    $loaderVariable = PHP_OS_FAMILY === 'Darwin' ? 'DYLD_LIBRARY_PATH' : 'LD_LIBRARY_PATH';
    $existing = $environment[$loaderVariable] ?? '';
    $environment[$loaderVariable] = $phpxHome . '/lib'
        . ($existing === '' ? '' : PATH_SEPARATOR . $existing);
}
$typephp = parseBridgeResults(runBridgeCommand([$binary], $root, $environment));

echo "Metric                 PHP ns/op  TypePHP ns/op  TypePHP/PHP\n";
echo "------------------------------------------------------------\n";
$metrics = [
    'pure_int',
    'function_call',
    'method_call',
    'property_access',
    'magic_call',
    'magic_property',
    'array_append',
    'string_concat',
];
if ($selectedCase !== null && $selectedCase !== '') {
    $metrics = [$selectedCase];
}
foreach ($metrics as $metric) {
    if (!isset($php[$metric], $typephp[$metric])) {
        throw new RuntimeException("Missing benchmark metric: {$metric}");
    }
    printf(
        "%-22s %10.2f %14.2f %12.2fx\n",
        $metric,
        $php[$metric],
        $typephp[$metric],
        $typephp[$metric] / $php[$metric],
    );
}
