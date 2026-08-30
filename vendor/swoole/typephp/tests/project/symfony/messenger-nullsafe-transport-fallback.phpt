--TEST--
Symfony Messenger pattern: nullsafe transport fallback with throw expression
--FILE--
<?php

final class SentToFailureTransportStamp
{
    public function __construct(private string $originalReceiverName)
    {
    }

    public function getOriginalReceiverName(): string
    {
        return $this->originalReceiverName;
    }
}

final class ReceivedStamp
{
    public function __construct(private string $transportName)
    {
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }
}

final class Envelope
{
    public function __construct(private array $stamps)
    {
    }

    public function last(string $class): ?object
    {
        foreach (array_reverse($this->stamps) as $stamp) {
            if ($stamp instanceof $class) {
                return $stamp;
            }
        }

        return null;
    }
}

function transport_name(Envelope $envelope): string
{
    return $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName()
        ?? $envelope->last(ReceivedStamp::class)?->getTransportName()
        ?? throw new LogicException('A ReceivedStamp is required.');
}

function main(): void
{
    var_dump(transport_name(new Envelope([new ReceivedStamp('async')])));
    var_dump(transport_name(new Envelope([new ReceivedStamp('async'), new SentToFailureTransportStamp('failed')])));

    try {
        transport_name(new Envelope([]));
    } catch (LogicException $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(5) "async"
string(6) "failed"
string(28) "A ReceivedStamp is required."
