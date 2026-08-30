<?php

namespace TypePhp\Cli;

final class CompletionCommand
{
    /** Return null for a normal compiler invocation, otherwise an exit status. */
    public static function execute(array $argv): ?int
    {
        $matches = array_values(array_filter(
            array_slice($argv, 1),
            static fn (mixed $argument): bool => is_string($argument)
                && str_starts_with($argument, '--generate-completion'),
        ));
        if ($matches === []) {
            return null;
        }
        if (count($argv) !== 2 || count($matches) !== 1 || $matches[0] !== '--generate-completion=bash') {
            fwrite(STDERR, "Usage: {$argv[0]} --generate-completion=bash" . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, BashCompletion::render());
        return 0;
    }
}
