<?php
class ReadonlyParent
{
    protected readonly int $value;
}

class ReadonlyChild extends ReadonlyParent
{
    public function __construct()
    {
        $this->value = 1;
    }
}
