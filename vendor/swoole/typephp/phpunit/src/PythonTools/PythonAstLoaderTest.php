<?php

namespace TypePhpTest\PythonTools;

use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\PythonTools\Converter\PythonAstLoader;
use TypePhp\PythonTools\Converter\PythonToTypePhpConverter;

/**
 * PythonAstLoader（python3 子进程 AST 解析）与转换器注入点的测试。
 *
 * 真实 python3 路径在环境缺失 python3 时跳过；loader 的 JSON 边界分支
 * （非 Module 根、无效 JSON）无法通过真实 python3 触发，依靠子类化替身覆盖。
 */
final class PythonAstLoaderTest extends TestCase
{
    private static ?bool $pythonAvailable = null;

    protected function setUp(): void
    {
        self::$pythonAvailable ??= $this->detectPython();
    }

    private function detectPython(): bool
    {
        $process = @proc_open(['python3', '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        return proc_close($process) === 0;
    }

    private function requirePython(): void
    {
        if (!self::$pythonAvailable) {
            self::markTestSkipped('python3 is required for this test');
        }
    }

    public function testParsesModuleAstFromRealPython(): void
    {
        $this->requirePython();

        $tree = (new PythonAstLoader())->parse("x = 1\n", 'ok.py');

        self::assertSame('Module', $tree['_type']);
        self::assertIsArray($tree['body']);
        self::assertSame('Assign', $tree['body'][0]['_type']);
        self::assertSame(1, $tree['body'][0]['lineno']);
    }

    public function testSyntaxErrorReportsFileAndLine(): void
    {
        $this->requirePython();

        try {
            (new PythonAstLoader())->parse("def broken(:\n", 'bad.py');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Unable to parse Python source', $exception->getMessage());
            self::assertStringContainsString('bad.py:1', $exception->getMessage());
        }
    }

    /** proc_open 对不存在的二进制会触发 PHP Warning，需要绕过 PHPUnit 错误处理器。 */
    #[WithoutErrorHandler]
    public function testMissingPythonExecutable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to start Python executable');

        (new PythonAstLoader('/nonexistent/python3-bin'))->parse("x = 1\n", 'x.py');
    }

    public function testConverterAcceptsInjectedLoader(): void
    {
        $loader = new class extends PythonAstLoader {
            public function parse(string $source, string $filename): array
            {
                return ['_type' => 'Module', 'body' => []];
            }
        };

        $php = (new PythonToTypePhpConverter($loader))->convertSource('ignored', 'fake.py');

        self::assertStringContainsString('/** @generated from fake.py */', $php);
        self::assertStringContainsString('function main(): void', $php);
    }

    public function testConverterPropagatesLoaderFailure(): void
    {
        $loader = new class extends PythonAstLoader {
            public function parse(string $source, string $filename): array
            {
                throw new RuntimeException('Unable to parse Python source: broken.py:1: boom');
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('broken.py:1: boom');

        (new PythonToTypePhpConverter($loader))->convertSource('ignored', 'broken.py');
    }
}
