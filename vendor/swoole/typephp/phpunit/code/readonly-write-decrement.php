<?php
class ReadonlyDecrementWrite
{
    public readonly int $value;
    public function __construct() { $this->value = 1; }
    public function change(): void { $this->value--; }
}
