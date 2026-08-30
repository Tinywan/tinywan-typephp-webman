<?php
class ReadonlyReferenceFetch
{
    public readonly int $value;

    public function __construct()
    {
        $this->value = 1;
        $reference =& $this->value;
    }
}
