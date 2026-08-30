--TEST--
Symfony pattern: nullable callable converted to first-class callable
--FILE--
<?php

class SymfonyLikeInput
{
    private ?Closure $onEmpty = null;
    private array $input = [];

    public function onEmpty(?callable $onEmpty = null): void
    {
        $this->onEmpty = null !== $onEmpty ? $onEmpty(...) : null;
    }

    public function write(string $value): void
    {
        $this->input[] = $value;
    }

    public function next(): ?string
    {
        if (!$this->input && null !== $onEmpty = $this->onEmpty) {
            $this->write($onEmpty($this));
        }

        return array_shift($this->input);
    }
}

function main(): void
{
    $stream = new SymfonyLikeInput();
    $stream->onEmpty(static fn (SymfonyLikeInput $input): string => 'generated');
    var_dump($stream->next());

    $stream->onEmpty(null);
    var_dump($stream->next());
}
?>
--EXPECT--
string(9) "generated"
NULL
