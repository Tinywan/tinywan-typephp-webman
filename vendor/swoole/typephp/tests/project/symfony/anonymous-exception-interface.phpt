--TEST--
Symfony pattern: anonymous exception class implements marker interface
--FILE--
<?php

interface SymfonyLikeNotFoundException
{
}

function createNotFoundException(string $id): Throwable
{
    return new class(sprintf('Service "%s" not found.', $id)) extends InvalidArgumentException implements SymfonyLikeNotFoundException {
    };
}

function main(): void
{
    $exception = createNotFoundException('mailer');

    var_dump($exception instanceof InvalidArgumentException);
    var_dump($exception instanceof SymfonyLikeNotFoundException);
    var_dump($exception->getMessage());
}
?>
--EXPECT--
bool(true)
bool(true)
string(27) "Service "mailer" not found."
