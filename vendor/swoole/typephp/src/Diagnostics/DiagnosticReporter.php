<?php

namespace TypePhp\Diagnostics;

use PhpParser\Node;

interface DiagnosticReporter
{
    public function fatal(string $message): never;

    public function warning(Node $node, string $file, string $message): void;
}
