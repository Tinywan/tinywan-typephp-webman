<?php
trait RequiresConversion
{
    abstract public function convert(int $value): iterable;

    abstract public function label(int|string $value): string;
}

// A valid implementation does not need to be textually identical to the
// requirement: parameters are contravariant, returns are covariant, extra
// optional parameters are allowed, and Zend places no visibility constraint
// on the implementation of an abstract trait requirement.
class ValidImplementation
{
    use RequiresConversion;

    public function convert(int|float $value, int $extra = 0): array
    {
        return [$value, $extra];
    }

    protected function label(int|string|float $value): string
    {
        return "$value";
    }
}

trait NeedsMaker
{
    abstract public function make(int $value): iterable;
}

trait HasMaker
{
    public function make(int|string $value): array
    {
        return [$value];
    }
}

class TraitFulfillsTrait
{
    use NeedsMaker, HasMaker;
}

class TraitFulfillsTraitReversed
{
    use HasMaker, NeedsMaker;
}

function main() {}
