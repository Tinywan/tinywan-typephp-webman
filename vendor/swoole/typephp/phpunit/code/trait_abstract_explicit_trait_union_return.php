<?php
trait ExplicitTraitUnionRequirement
{
    abstract public function make(): ExplicitTraitUnionRequirement|null;
}

class InvalidExplicitTraitUnionReturn
{
    use ExplicitTraitUnionRequirement;

    public function make(): self|null
    {
        return $this;
    }
}

function main() {}
