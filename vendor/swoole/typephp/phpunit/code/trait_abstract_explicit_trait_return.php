<?php
trait ExplicitTraitReturnRequirement
{
    abstract public function make(): ExplicitTraitReturnRequirement;
}

class InvalidExplicitTraitReturn
{
    use ExplicitTraitReturnRequirement;

    public function make(): self
    {
        return $this;
    }
}

function main() {}
