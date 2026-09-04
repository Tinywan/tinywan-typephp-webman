<?php

use PHPUnit\Framework\TestCase;
use TypePhp\Cli\BashCompletion;
use TypePhp\Cli\CompletionCommand;
use TypePhp\Cli\CompletionMetadata;
use TypePhp\Metadata\Constants;

final class BashCompletionTest extends TestCase
{
    public function testGeneratedFileMatchesRenderer(): void
    {
        self::assertSame(
            BashCompletion::render(),
            file_get_contents(TYPEPHP_ROOT_PATH . '/completions/tpc.bash'),
        );
    }

    public function testEveryPublicCompilerOptionIsCompleted(): void
    {
        $options = CompletionMetadata::options();
        foreach (Constants::COMPILER_OPTIONS as $name => $definition) {
            if ($name === 'debug-line') {
                continue;
            }
            if (isset($definition['prefix'])) {
                self::assertContains('-' . $definition['prefix'], $options);
            }
            if (isset($definition['longPrefix'])) {
                self::assertContains('--' . $definition['longPrefix'], $options);
            }
        }
    }

    public function testCompletionCommandOnlyAcceptsBash(): void
    {
        self::assertNull(CompletionCommand::execute(['tpc', 'hello.php']));
        self::assertSame(
            BashCompletion::render(),
            $this->runBash(
                escapeshellarg(PHP_BINARY)
                . ' bin/tpc.php --generate-completion=bash',
            ),
        );
    }

    public function testBashScriptSyntaxAndRepresentativeCompletions(): void
    {
        $script = TYPEPHP_ROOT_PATH . '/completions/tpc.bash';
        self::assertSame('', $this->runBash("bash -n " . escapeshellarg($script)));
        self::assertStringNotContainsString('mapfile', file_get_contents($script));
        self::assertSame(
            "--wasm=component\n",
            $this->complete($script, ['tpc', '--wasm=c']),
        );
        self::assertSame(
            "bin\nlib\next\n",
            $this->complete($script, ['tpc', '--mode', '']),
        );
        self::assertSame(
            "8.4\n",
            $this->complete($script, ['tpc', '--php-version', '8.4']),
        );
        self::assertSame(
            "8.4\n8.5\n",
            $this->complete($script, ['tpc', '--php-version', '']),
        );
        self::assertSame(
            "-O2\n",
            $this->complete($script, ['tpc', '-O2']),
        );
    }

    /**
     * --compiler must complete commands, not the default *.php/*.yml file set.
     *
     * Prefixes are chosen to exist on any POSIX box: "ec" matches the bash
     * builtin `echo`, and /usr/bin/ always holds executables.
     */
    public function testCompilerOptionCompletesCommandsAndExecutablePaths(): void
    {
        $script = TYPEPHP_ROOT_PATH . '/completions/tpc.bash';

        $names = $this->complete($script, ['tpc', '--compiler', 'ec']);
        self::assertNotSame('', $names);
        self::assertStringContainsString('echo', $names);
        foreach (explode("\n", trim($names)) as $candidate) {
            self::assertDoesNotMatchRegularExpression('/\.(php|yml|yaml|prof)$/', $candidate);
        }

        // Once the word contains a slash, completion switches to that directory.
        $paths = $this->complete($script, ['tpc', '--compiler', '/usr/bin/']);
        self::assertNotSame('', $paths);
        foreach (explode("\n", trim($paths)) as $candidate) {
            self::assertStringStartsWith('/usr/bin/', $candidate);
        }

        // The --compiler= form keeps the prefix on every candidate.
        $equals = $this->complete($script, ['tpc', '--compiler=ec']);
        self::assertNotSame('', $equals);
        foreach (explode("\n", trim($equals)) as $candidate) {
            self::assertStringStartsWith('--compiler=', $candidate);
        }
    }

    /** @param list<string> $words */
    private function complete(string $script, array $words): string
    {
        $assignments = [];
        foreach ($words as $word) {
            $assignments[] = escapeshellarg($word);
        }
        $command = 'source ' . escapeshellarg($script)
            . '; compopt() { :; }'
            . '; COMP_WORDS=(' . implode(' ', $assignments) . ')'
            . '; COMP_CWORD=' . (count($words) - 1)
            . '; _typephp_tpc'
            . '; printf "%s\\n" "${COMPREPLY[@]}"';
        return $this->runBash('bash -c ' . escapeshellarg($command));
    }

    private function runBash(string $command): string
    {
        exec($command . ' 2>&1', $output, $status);
        self::assertSame(0, $status, implode(PHP_EOL, $output));
        return $output === [] ? '' : implode(PHP_EOL, $output) . PHP_EOL;
    }
}
