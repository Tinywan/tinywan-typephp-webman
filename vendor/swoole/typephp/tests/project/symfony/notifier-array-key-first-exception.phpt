--TEST--
Symfony Notifier pattern: array_key_first selects wrapped exception
--FILE--
<?php

final class HandlerFailedException extends RuntimeException
{
    public function __construct(private array $exceptions)
    {
        parent::__construct('handler failed');
    }

    public function getWrappedExceptions(): array
    {
        return $this->exceptions;
    }
}

function unwrap_throwable(Throwable $throwable): Throwable
{
    if ($throwable instanceof HandlerFailedException) {
        $exceptions = $throwable->getWrappedExceptions();
        $throwable = $exceptions[array_key_first($exceptions)];
    }

    return $throwable;
}

function main(): void
{
    $throwable = new HandlerFailedException([
        'mail' => new InvalidArgumentException('bad address'),
        'sms' => new LogicException('not sent'),
    ]);

    $unwrapped = unwrap_throwable($throwable);
    echo get_class($unwrapped), ': ', $unwrapped->getMessage(), "\n";
}
?>
--EXPECT--
InvalidArgumentException: bad address
