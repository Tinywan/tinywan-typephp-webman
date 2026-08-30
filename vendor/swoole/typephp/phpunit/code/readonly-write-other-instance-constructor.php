<?php
class ReadonlyOtherInstance
{
    public readonly int $value;

    public function __construct(ReadonlyOtherInstance $other)
    {
        $other->value = 1;
    }
}
