<?php
trait RequiresValue
{
    abstract public function value(int $value): string;
}

class InvalidImplementation
{
    use RequiresValue;

    public function value(string $value): string
    {
        return $value;
    }
}

function main() {}
