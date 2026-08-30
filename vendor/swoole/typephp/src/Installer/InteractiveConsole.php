<?php

namespace TypePhp\Installer;

final class InteractiveConsole
{
    public function isInteractive(): bool
    {
        return defined('STDIN') && function_exists('stream_isatty') && stream_isatty(STDIN);
    }

    public function confirm(string $question, bool $default = true): bool
    {
        $suffix = $default ? ' [Y/n] ' : ' [y/N] ';
        $answer = strtolower(trim($this->ask($question . $suffix, '')));
        return $answer === '' ? $default : in_array($answer, ['y', 'yes'], true);
    }

    public function ask(string $question, string $default): string
    {
        fwrite(STDERR, $question);
        $value = trim((string) fgets(STDIN));
        return $value === '' ? $default : $value;
    }

    public function write(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
