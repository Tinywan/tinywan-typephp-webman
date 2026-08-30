<?php
function mutate_readonly_argument(int &$value): void
{
    $value++;
}

class ReadonlyReferenceCallArgument
{
    public readonly int $value;

    public function __construct()
    {
        $this->value = 1;
        mutate_readonly_argument($this->value);
    }
}
