<?php
class ReadonlyArrayDimWrite
{
    public readonly array $value;
    public function __construct() { $this->value = []; }
    public function change(): void { $this->value[] = 1; }
}
