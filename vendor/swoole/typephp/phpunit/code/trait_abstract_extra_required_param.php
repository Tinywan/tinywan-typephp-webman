<?php
trait RequiresValue
{
    abstract public function value(int $value): string;
}

class GreedyImplementation
{
    use RequiresValue;

    public function value(int $value, int $extra): string
    {
        return "$value:$extra";
    }
}

function main() {}
