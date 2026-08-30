--TEST--
Symfony pattern: callable variable converted to first-class callable
--FILE--
<?php

class SymfonyLikeLogger
{
    private Closure $formatter;

    public function __construct(?callable $formatter = null)
    {
        $this->formatter = null !== $formatter ? $formatter(...) : $this->format(...);
    }

    public function log(string $level, string $message): string
    {
        return ($this->formatter)($level, $message);
    }

    private function format(string $level, string $message): string
    {
        return strtoupper($level).': '.$message;
    }
}

function main(): void
{
    $default = new SymfonyLikeLogger();
    $custom = new SymfonyLikeLogger(static fn (string $level, string $message): string => $level.'|'.strrev($message));

    var_dump($default->log('info', 'boot'));
    var_dump($custom->log('debug', 'boot'));
}
?>
--EXPECT--
string(10) "INFO: boot"
string(10) "debug|toob"
