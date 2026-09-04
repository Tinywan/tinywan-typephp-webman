<?php
trait RequiresValue
{
    abstract public function value(): string;
}

class StaticImplementation
{
    use RequiresValue;

    public static function value(): string
    {
        return 'value';
    }
}

function main() {}
