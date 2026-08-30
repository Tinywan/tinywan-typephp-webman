<?php
class ImmutablePropertyByRefBuiltin
{
    private array $values = [2, 1];

    #[Immutable]
    public function sorted(): void
    {
        sort($this->values);
    }
}
