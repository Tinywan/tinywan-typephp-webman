<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the process exit code of AOT-compiled binaries.
 *
 * PHP CLI semantics require:
 * - explicit exit(N)        -> process exits with N
 * - uncaught exception      -> process exits with 255
 * - fatal error             -> process exits with 255
 * - normal completion       -> process exits with 0
 */
class ExitCodeTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir() . '/typephp-exitcode-' . bin2hex(random_bytes(6));
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
        parent::tearDown();
    }

    public function testExplicitExitCodeIsPreserved(): void
    {
        $exitCode = $this->buildAndRun(<<<'PHP'
<?php

declare(strict_types=1);

function main(): void
{
    exit(3);
}
PHP);

        $this->assertSame(3, $exitCode);
    }

    public function testUncaughtExceptionExitsWith255(): void
    {
        $exitCode = $this->buildAndRun(<<<'PHP'
<?php

declare(strict_types=1);

function main(): void
{
    throw new RuntimeException('intentional uncaught exception');
}
PHP);

        $this->assertSame(255, $exitCode);
    }

    public function testFatalErrorExitsWith255(): void
    {
        $exitCode = $this->buildAndRun(<<<'PHP'
<?php

declare(strict_types=1);

function main(): void
{
    trigger_error('intentional fatal error', E_USER_ERROR);
}
PHP);

        $this->assertSame(255, $exitCode);
    }

    public function testNormalCompletionExitsWithZero(): void
    {
        $exitCode = $this->buildAndRun(<<<'PHP'
<?php

declare(strict_types=1);

function main(): void
{
    echo "ok\n";
}
PHP);

        $this->assertSame(0, $exitCode);
    }

    /**
     * Compile a single-file bin project and return the exit code of the binary.
     */
    private function buildAndRun(string $source): int
    {
        $srcDir = $this->workDir . '/src';
        $binDir = $this->workDir . '/bin';
        $buildDir = $this->workDir . '/build';
        mkdir($srcDir, 0777, true);
        mkdir($binDir, 0777, true);

        file_put_contents($srcDir . '/main.php', $source);
        file_put_contents($srcDir . '/project.yml', sprintf(
            "name: exit-code-test\n"
            . "version: 0.1.0\n"
            . "mode: bin\n"
            . "output: %s/exit_code_test\n"
            . "build-dir: %s\n"
            . "optimize: 0\n"
            . "no-progress: true\n"
            . "\n"
            . "sources:\n"
            . "  - main.php\n",
            $binDir,
            $buildDir
        ));

        $tpc = dirname(__DIR__, 2) . '/bin/tpc.php';
        $buildCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tpc)
            . ' ' . escapeshellarg($srcDir . '/project.yml') . ' -j 1';
        exec($buildCommand . ' 2>&1', $buildOutput, $buildExitCode);
        $this->assertSame(
            0,
            $buildExitCode,
            'AOT compilation failed: ' . implode("\n", $buildOutput)
        );

        $binary = $binDir . '/exit_code_test';
        exec(escapeshellarg($binary) . ' 2>&1', $runOutput, $exitCode);

        return $exitCode;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
