--TEST--
Symfony pattern: error handler arrow function throws conditional exception
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

class SymfonyLikeCompiledUrlMatcherDumper
{
    private RuntimeException $signalingException;

    public function __construct()
    {
        $this->signalingException = new RuntimeException('SIGNAL');
    }

    public function run(string $message): void
    {
        set_error_handler(fn ($type, $errorMessage) => throw str_contains($errorMessage, $this->signalingException->getMessage()) ? $this->signalingException : new ErrorException($errorMessage));

        try {
            trigger_error($message, E_USER_WARNING);
        } finally {
            restore_error_handler();
        }
    }
}

function main(): void
{
    $dumper = new SymfonyLikeCompiledUrlMatcherDumper();

    foreach (['SIGNAL: stop', 'plain failure'] as $message) {
        try {
            $dumper->run($message);
        } catch (Throwable $e) {
            var_dump($e::class);
            var_dump($e->getMessage());
        }
    }
}
?>
--EXPECT--
string(16) "RuntimeException"
string(6) "SIGNAL"
string(14) "ErrorException"
string(13) "plain failure"
