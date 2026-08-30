<?php
class ReadonlyConcatWrite
{
    public readonly string $value;
    public function __construct() { $this->value = 'a'; }
    public function change(): void { $this->value .= 'b'; }
}
