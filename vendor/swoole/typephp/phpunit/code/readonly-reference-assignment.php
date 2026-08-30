<?php
class ReadonlyReferenceAssignment
{
    public readonly int $value;

    public function __construct(int &$source)
    {
        $this->value =& $source;
    }
}
