--TEST--
Runtime modulo result can be assigned to an optimized int property
--FILE--
<?php

namespace RuntimeModuloNamespace {

final class Cursor
{
    private array $items = [];
    private int $selected = 5;

    public function refresh(callable $provider): void
    {
        $this->items = $provider();
    }

    public function move(): void
    {
        $count = count($this->items);
        $this->selected = $this->selected % $count;
    }

    public function selected(): int
    {
        return $this->selected;
    }
}

}

namespace {

function main(): void
{
    $cursor = new RuntimeModuloNamespace\Cursor();
    $cursor->refresh(fn(): array => [1, 2]);
    $cursor->move();
    var_dump($cursor->selected());
}

}
?>
--EXPECT--
int(1)
