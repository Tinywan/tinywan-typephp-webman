<?php
class ReadonlyForeachWrite
{
    public readonly int $value;
    public function __construct() { $this->value = 1; }
    public function change(): void
    {
        foreach ([2] as $this->value) {}
    }
}
