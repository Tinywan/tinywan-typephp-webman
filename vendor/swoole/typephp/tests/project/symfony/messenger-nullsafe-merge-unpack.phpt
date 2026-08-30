--TEST--
Symfony Messenger style nullsafe fallback and with(...array_merge(...array_values()))
--FILE--
<?php
class SymfonyOriginalReceiverStamp
{
    public function __construct(private string $name)
    {
    }

    public function getOriginalReceiverName(): string
    {
        return $this->name;
    }
}

class SymfonyReceivedStamp
{
    public function __construct(private string $name)
    {
    }

    public function getTransportName(): string
    {
        return $this->name;
    }
}

class SymfonyEnvelopeMergeCase
{
    private array $stamps = [];

    public function with(object ...$stamps): self
    {
        $clone = clone $this;
        foreach ($stamps as $stamp) {
            $clone->stamps[$stamp::class][] = $stamp;
        }

        return $clone;
    }

    public function last(string $class): ?object
    {
        $stamps = $this->stamps[$class] ?? [];

        return $stamps[count($stamps) - 1] ?? null;
    }

    public function all(): array
    {
        return $this->stamps;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->stamps as $items) {
            $count += count($items);
        }

        return $count;
    }
}

function symfony_decode_transport(SymfonyEnvelopeMergeCase $envelope): string
{
    return $envelope->last(SymfonyOriginalReceiverStamp::class)?->getOriginalReceiverName()
        ?? $envelope->last(SymfonyReceivedStamp::class)?->getTransportName()
        ?? throw new LogicException('A ReceivedStamp is required.');
}

function main(): void
{
    $envelope = (new SymfonyEnvelopeMergeCase())
        ->with(new SymfonyReceivedStamp('async'), new SymfonyOriginalReceiverStamp('failed'));
    $decoded = new SymfonyEnvelopeMergeCase();

    $merged = $decoded->with(...array_merge(...array_values($envelope->all())));

    var_dump(symfony_decode_transport($envelope));
    var_dump($merged->count());

    try {
        symfony_decode_transport(new SymfonyEnvelopeMergeCase());
    } catch (LogicException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
string(6) "failed"
int(2)
A ReceivedStamp is required.
