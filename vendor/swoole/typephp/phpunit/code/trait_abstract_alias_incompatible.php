<?php
trait RequiresValue
{
    abstract public function value(int $value): string;
}

// Aliasing an abstract method creates the requirement under the new name;
// the class method defined under that name must satisfy it.
class AliasImplementation
{
    use RequiresValue { value as renamed; }

    public function renamed(string $value): string
    {
        return $value;
    }

    public function value(int $value): string
    {
        return "$value";
    }
}

function main() {}
