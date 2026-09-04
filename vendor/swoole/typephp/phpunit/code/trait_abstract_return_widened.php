<?php
trait RequiresValue
{
    abstract public function value(): string;
}

class WideningImplementation
{
    use RequiresValue;

    public function value(): string|int
    {
        return 'value';
    }
}

function main() {}
