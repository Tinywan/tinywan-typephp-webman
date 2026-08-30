--TEST--
Symfony pattern: nullsafe call with coalesce throw expression
--FILE--
<?php

class SymfonyLikeReceivedStamp
{
    public function __construct(private string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}

function stampId(?SymfonyLikeReceivedStamp $stamp): string
{
    return $stamp?->getId() ?? throw new LogicException('No stamp found.');
}

function main(): void
{
    var_dump(stampId(new SymfonyLikeReceivedStamp('abc')));

    try {
        stampId(null);
    } catch (Throwable $e) {
        var_dump($e::class);
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(3) "abc"
string(14) "LogicException"
string(15) "No stamp found."
