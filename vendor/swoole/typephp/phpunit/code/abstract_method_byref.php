<?php
abstract class AbstractByRefBase
{
    public function __construct()
    {
        $this->abc($value);
        var_dump($value);
    }

    abstract public function abc(&$value);
}

class AbstractByRefChild extends AbstractByRefBase
{
    public function abc(&$value)
    {
        $value = 1;
    }
}

function main()
{
    new AbstractByRefChild;
}
