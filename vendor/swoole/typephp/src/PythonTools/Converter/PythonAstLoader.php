<?php

namespace TypePhp\PythonTools\Converter;

use RuntimeException;

/**
 * Intentionally non-final so tests can subclass it to inject a canned AST or simulate a parse failure;
 * see phpunit/src/PythonTools/PythonAstLoaderTest.php.
 */
class PythonAstLoader
{
    private const DUMPER = <<<'PYTHON'
import ast
import json
import sys

def convert(value):
    if isinstance(value, ast.AST):
        result = {name: convert(item) for name, item in ast.iter_fields(value)}
        result['_type'] = value.__class__.__name__
        for name in ('lineno', 'col_offset', 'end_lineno', 'end_col_offset'):
            if hasattr(value, name):
                result[name] = getattr(value, name)
        return result
    if isinstance(value, list):
        return [convert(item) for item in value]
    if isinstance(value, bytes):
        return {'_python_constant': 'bytes', 'hex': value.hex()}
    if isinstance(value, complex):
        return {'_python_constant': 'complex', 'real': value.real, 'imag': value.imag}
    return value

filename = sys.argv[1]
source = sys.stdin.read()
try:
    tree = ast.parse(source, filename=filename, type_comments=True)
except SyntaxError as error:
    print(json.dumps({
        'error': error.msg,
        'line': error.lineno,
        'column': error.offset,
    }), file=sys.stderr)
    raise SystemExit(2)
print(json.dumps(convert(tree), ensure_ascii=False))
PYTHON;

    public function __construct(private readonly string $python = 'python3')
    {
    }

    /** @return array<string, mixed> */
    public function parse(string $source, string $filename): array
    {
        $command = [$this->python, '-c', self::DUMPER, $filename];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        // Do not inherit the project directory as Python's import root: a user
        // file such as ast.py must not shadow the standard-library ast module.
        $process = proc_open($command, $descriptors, $pipes, sys_get_temp_dir());
        if (!is_resource($process)) {
            throw new RuntimeException("Unable to start Python executable `{$this->python}`");
        }
        fwrite($pipes[0], $source);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            $detail = trim($stderr);
            try {
                $error = json_decode($detail, true, flags: JSON_THROW_ON_ERROR);
                if (is_array($error) && isset($error['error'])) {
                    $detail = $filename . ':' . ($error['line'] ?? 0) . ': ' . $error['error'];
                }
            } catch (\JsonException) {
            }
            throw new RuntimeException('Unable to parse Python source: ' . ($detail !== '' ? $detail : "exit status {$status}"));
        }
        try {
            $tree = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Python AST dumper returned invalid JSON', 0, $exception);
        }
        if (!is_array($tree) || ($tree['_type'] ?? null) !== 'Module') {
            throw new RuntimeException('Python AST dumper did not return a module');
        }
        return $tree;
    }
}
