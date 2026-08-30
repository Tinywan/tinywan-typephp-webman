<?php

readonly class ReadonlyClassPropertyUnset
{
    public int $value;

    public function __construct()
    {
        $this->value = 1;
    }

    public function clear(): void
    {
        unset($this->value);
    }
}
