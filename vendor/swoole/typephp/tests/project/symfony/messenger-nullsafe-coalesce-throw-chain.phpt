--TEST--
Symfony Messenger style nullsafe chain with coalesce throw
--FILE--
<?php
class MissingStampException extends Exception {}

class TransportStamp
{
    public function __construct(private string $id) {}

    public function getId(): string
    {
        return $this->id;
    }
}

class Envelope
{
    public function __construct(private ?TransportStamp $stamp) {}

    public function last(string $class): ?object
    {
        return $this->stamp instanceof $class ? $this->stamp : null;
    }
}

function stampId(Envelope $envelope): string
{
    return $envelope->last(TransportStamp::class)?->getId() ?? throw new MissingStampException('No stamp found.');
}

function main(): void
{
    var_dump(stampId(new Envelope(new TransportStamp('abc'))));
    try {
        stampId(new Envelope(null));
    } catch (MissingStampException $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(3) "abc"
string(15) "No stamp found."
