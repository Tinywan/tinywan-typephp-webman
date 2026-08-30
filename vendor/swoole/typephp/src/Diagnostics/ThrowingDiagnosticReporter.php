<?php

namespace TypePhp\Diagnostics;

use PhpParser\Node;
use TypePhp\Exception\TestError;

final class ThrowingDiagnosticReporter implements DiagnosticReporter
{
    public function fatal(string $message): never
    {
        throw new TestError($message);
    }

    public function warning(Node $node, string $file, string $message): void
    {
    }
}
