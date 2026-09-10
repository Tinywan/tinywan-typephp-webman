<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;

final class CliUsageTest extends TestCase
{
    public function testHelpSeparatesSubcommandsFromCompilationOptions(): void
    {
        $process = proc_open(
            [PHP_BINARY, TYPEPHP_ROOT_PATH . '/bin/tpc.php', '--help'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            TYPEPHP_ROOT_PATH,
        );
        self::assertIsResource($process);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        self::assertSame(0, $status, $error);
        self::assertIsString($output);

        $subcommands = self::section($output, 'SUBCOMMANDS:', 'EXAMPLES:');
        self::assertStringContainsString('--gen-python-helper', $subcommands);
        self::assertStringContainsString('--convert-python-to-php', $subcommands);
        self::assertStringContainsString('--generate-completion=bash', $subcommands);

        $compilationOptions = self::section($output, 'COMPILATION OPTIONS:', 'GENERAL OPTIONS:');
        self::assertStringContainsString('--nano', $compilationOptions);
        self::assertStringContainsString('--wasm[=browser|component]', $compilationOptions);
        self::assertStringNotContainsString('--gen-python-helper', $compilationOptions);
        self::assertStringNotContainsString('--convert-python-to-php', $compilationOptions);
        self::assertStringNotContainsString('--generate-completion', $compilationOptions);
    }

    private static function section(string $output, string $start, string $end): string
    {
        $startOffset = strpos($output, $start);
        $endOffset = strpos($output, $end);
        self::assertNotFalse($startOffset);
        self::assertNotFalse($endOffset);
        self::assertGreaterThan($startOffset, $endOffset);

        return substr($output, $startOffset, $endOffset - $startOffset);
    }
}
