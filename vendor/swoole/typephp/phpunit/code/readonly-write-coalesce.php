<?php
class ReadonlyCoalesceWrite
{
    public readonly ?int $value;
    public function __construct() { $this->value = null; }
    public function change(): void { $this->value ??= 1; }
}
