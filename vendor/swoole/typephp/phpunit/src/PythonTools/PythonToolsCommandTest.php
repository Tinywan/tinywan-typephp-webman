<?php

namespace TypePhpTest\PythonTools;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use TypePhp\PythonTools\Command;

final class PythonToolsCommandTest extends TestCase
{
    #[RequiresPhpExtension('phpy')]
    public function testCustomOutputDirectoryPreservesExistingPyObjectHelper(): void
    {
        $root = sys_get_temp_dir() . '/typephp-python-tools-' . bin2hex(random_bytes(6));
        $output = $root . '/stubs';
        self::assertTrue(mkdir($output, 0777, true));
        self::assertNotFalse(file_put_contents($output . '/PyObject.php', 'keep-me'));

        try {
            $status = Command::execute(
                ['tpc', Command::GENERATE_HELPER, 'math', '--output-dir', 'stubs'],
                $root,
            );

            self::assertSame(0, $status);
            self::assertFileExists($output . '/python/math.php');
            self::assertFileExists($output . '/python.php');
            $builtins = file_get_contents($output . '/python.php');
            self::assertIsString($builtins);
            self::assertStringContainsString('namespace python;', $builtins);
            self::assertStringContainsString('function tuple(', $builtins);
            self::assertStringContainsString(
                '// Omitted Python callable not representable as a PHP function: print',
                $builtins,
            );
            self::assertSame('keep-me', file_get_contents($output . '/PyObject.php'));
        } finally {
            @unlink($output . '/python/math.php');
            @rmdir($output . '/python');
            @unlink($output . '/python.php');
            @unlink($output . '/PyObject.php');
            @rmdir($output);
            @rmdir($root);
        }
    }

    // ---------------------------------------------------------------
    // --convert-python-to-php（py2php）CLI 层
    // ---------------------------------------------------------------

    /** 非 Python 工具子命令的普通编译器调用返回 null。 */
    public function testReturnsNullForRegularCompilerInvocation(): void
    {
        self::assertNull(Command::execute(['tpc', 'app.php', '-o', 'app']));
    }

    public function testConvertWritesGeneratedPhpToStdout(): void
    {
        $file = $this->writeTempPython("x = 1\nprint(x)\n");

        try {
            ['status' => $status, 'stdout' => $stdout] = $this->runTpc([Command::CONVERT_SOURCE, $file]);
        } finally {
            @unlink($file);
        }

        self::assertSame(0, $status);
        self::assertStringContainsString('/** @generated from ', $stdout);
        self::assertStringContainsString('function main(): void', $stdout);
        self::assertStringContainsString('global $x;', $stdout);
    }

    public function testConvertWithoutFileArgumentFails(): void
    {
        ['status' => $status, 'stderr' => $stderr] = $this->runTpc([Command::CONVERT_SOURCE]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Usage:', $stderr);
        self::assertStringContainsString(Command::CONVERT_SOURCE, $stderr);
    }

    public function testConvertWithExtraArgumentFails(): void
    {
        ['status' => $status, 'stderr' => $stderr] = $this->runTpc([Command::CONVERT_SOURCE, 'a.py', 'b.py']);

        self::assertSame(1, $status);
        self::assertStringContainsString('Usage:', $stderr);
    }

    public function testConvertUnreadableFileFails(): void
    {
        $file = sys_get_temp_dir() . '/typephp-no-such-' . bin2hex(random_bytes(4)) . '.py';
        ['status' => $status, 'stderr' => $stderr] = $this->runTpc([Command::CONVERT_SOURCE, $file]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Unable to read Python source file', $stderr);
    }

    public function testConvertReportsPythonSyntaxError(): void
    {
        $file = $this->writeTempPython("def broken(:\n");

        try {
            ['status' => $status, 'stderr' => $stderr] = $this->runTpc([Command::CONVERT_SOURCE, $file]);
        } finally {
            @unlink($file);
        }

        self::assertSame(1, $status);
        self::assertStringContainsString('Unable to parse Python source', $stderr);
    }

    public function testSubcommandsCannotBeCombined(): void
    {
        ['status' => $status, 'stderr' => $stderr] = $this->runTpc([
            Command::CONVERT_SOURCE,
            'a.py',
            Command::GENERATE_HELPER,
            'sys',
        ]);

        self::assertSame(1, $status);
        self::assertStringContainsString('cannot be combined', $stderr);
    }

    /**
     * 通过真实子进程运行 tpc，分别捕获 stdout/stderr 与退出码。
     *
     * @return array{status: int, stdout: string, stderr: string}
     */
    private function runTpc(array $arguments): array
    {
        $command = array_merge([PHP_BINARY, TYPEPHP_ROOT_PATH . '/bin/tpc.php'], $arguments);
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, null, ['suppress_errors' => true]);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);

        return ['status' => $status, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }

    private function writeTempPython(string $source): string
    {
        $file = sys_get_temp_dir() . '/typephp-py2php-' . bin2hex(random_bytes(6)) . '.py';
        self::assertNotFalse(file_put_contents($file, $source));
        return $file;
    }
}
