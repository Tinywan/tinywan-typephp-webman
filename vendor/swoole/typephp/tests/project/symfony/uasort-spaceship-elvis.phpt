--TEST--
Symfony pattern: uasort with spaceship and elvis fallback comparison
--FILE--
<?php

class SymfonyLikeAcceptItem
{
    public function __construct(private float $quality, private int $index, public string $name)
    {
    }

    public function getQuality(): float
    {
        return $this->quality;
    }

    public function getIndex(): int
    {
        return $this->index;
    }
}

function main(): void
{
    $items = [
        'json' => new SymfonyLikeAcceptItem(0.9, 2, 'json'),
        'html' => new SymfonyLikeAcceptItem(1.0, 1, 'html'),
        'xml' => new SymfonyLikeAcceptItem(0.9, 0, 'xml'),
    ];

    uasort($items, static fn ($a, $b) => $b->getQuality() <=> $a->getQuality() ?: $a->getIndex() <=> $b->getIndex());

    foreach ($items as $item) {
        var_dump($item->name);
    }
}
?>
--EXPECT--
string(4) "html"
string(3) "xml"
string(4) "json"
