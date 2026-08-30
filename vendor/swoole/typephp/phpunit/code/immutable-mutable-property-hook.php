<?php

class ImmutableMutablePropertyHook
{
    public string $value = 'value' {
        get => $this->value;
    }

    #[Immutable]
    public function read(): string
    {
        return $this->value;
    }
}
