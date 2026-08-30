<?php

namespace TypePhp\Diagnostics;

use League\CLImate\CLImate;
use PhpParser\Node;

final readonly class CliDiagnosticReporter implements DiagnosticReporter
{
    public function __construct(
        private CLImate $climate,
        private bool $printBacktrace = false,
    ) {
    }

    public function fatal(string $message): never
    {
        $this->climate->red("Fatal error: {$message}");
        if ($this->printBacktrace) {
            debug_print_backtrace();
        }
        exit(255);
    }

    public function warning(Node $node, string $file, string $message): void
    {
        $this->climate->magenta("{$message} in {$file}:{$node->getStartLine()}");
    }
}
