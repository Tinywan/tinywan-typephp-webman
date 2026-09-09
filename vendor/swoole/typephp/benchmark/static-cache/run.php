<?php


$root = dirname(__DIR__, 2);
$source = __DIR__ . '/benchmark.php';
$project = __DIR__ . '/project.yml';
$binary = __DIR__ . '/static_cache' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
$skipBuild = in_array('--skip-build', $argv, true);

/** @param list<string> $command */
function runStaticCacheCommand(array $command, string $cwd, bool $capture, ?array $environment = null): string
{
    $process = proc_open(
        $command,
        [STDIN, $capture ? ['pipe', 'w'] : STDOUT, $capture ? ['pipe', 'w'] : STDERR],
        $pipes,
        $cwd,
        $environment,
        ['bypass_shell' => true],
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start: ' . implode(' ', $command));
    }

    $output = $capture ? stream_get_contents($pipes[1]) : '';
    $error = $capture ? stream_get_contents($pipes[2]) : '';
    if ($capture) {
        fclose($pipes[1]);
        fclose($pipes[2]);
    }
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException(implode(' ', $command) . " failed ({$status})\n{$output}{$error}");
    }
    return $output;
}

/** @return array<string, float|string> */
function parseStaticCacheResult(string $output): array
{
    $result = [];
    foreach (explode("\n", trim($output)) as $line) {
        if (preg_match('/^([a-z_]+)=([0-9.]+)$/', $line, $matches)) {
            $result[$matches[1]] = str_starts_with($matches[1], 'checksum_')
                ? $matches[2]
                : (float) $matches[2];
        }
    }
    return $result;
}

$php = getenv('PHP_BIN') ?: PHP_BINARY;
$compilerPhp = getenv('TPC_PHP_BIN') ?: $php;
$probe = static fn (string $executable): string => trim(runStaticCacheCommand([
    $executable,
    '-n',
    '-r',
    'printf("%s;%d;%d;%d", PHP_VERSION, PHP_ZTS, PHP_DEBUG, PHP_INT_SIZE);',
], $root, true));
$phpRuntime = $probe($php);
$compilerRuntime = $probe($compilerPhp);
if ($phpRuntime !== $compilerRuntime) {
    throw new RuntimeException("PHP ABI mismatch: PHP_BIN={$phpRuntime}; TPC_PHP_BIN={$compilerRuntime}");
}

if (!$skipBuild) {
    runStaticCacheCommand([
        $compilerPhp,
        '-n',
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

$phpResult = parseStaticCacheResult(runStaticCacheCommand([
    $php,
    '-n',
    '-d',
    'opcache.enable_cli=0',
    '-d',
    'opcache.jit=0',
    '-r',
    'require ' . var_export($source, true) . '; main();',
], $root, true));

$environment = getenv();
if (PHP_OS_FAMILY !== 'Windows') {
    $phpxHome = getenv('PHPX_HOME') ?: dirname($root) . '/phpx';
    $phpHome = dirname(dirname(realpath($compilerPhp) ?: $compilerPhp));
    $loader = PHP_OS_FAMILY === 'Darwin' ? 'DYLD_LIBRARY_PATH' : 'LD_LIBRARY_PATH';
    $existing = $environment[$loader] ?? '';
    $environment[$loader] = $phpxHome . '/lib' . PATH_SEPARATOR . $phpHome . '/lib'
        . ($existing === '' ? '' : PATH_SEPARATOR . $existing);
}
$typephpResult = parseStaticCacheResult(runStaticCacheCommand([$binary], $root, true, $environment));

echo "Runtime: {$phpRuntime}\n";
echo "Metric        PHP ns/op  TypePHP ns/op  TypePHP/PHP\n";
echo "--------------------------------------------------\n";
foreach (['explicit_read', 'self_read', 'self_write', 'get_data', 'get_table'] as $case) {
    $metric = $case . '_ns';
    $checksum = 'checksum_' . $case;
    if (!isset($phpResult[$metric], $typephpResult[$metric])) {
        throw new RuntimeException("Missing benchmark metric: {$case}");
    }
    if (($phpResult[$checksum] ?? null) !== ($typephpResult[$checksum] ?? null)) {
        throw new RuntimeException("Checksum mismatch: {$case}");
    }
    printf(
        "%-12s %10.2f %14.2f %12.2fx\n",
        $case,
        $phpResult[$metric],
        $typephpResult[$metric],
        $typephpResult[$metric] / $phpResult[$metric],
    );
}
