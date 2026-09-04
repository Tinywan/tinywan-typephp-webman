<?php
trait ExplicitTraitParameterRequirement
{
    abstract public function accept(ExplicitTraitParameterRequirement $value): void;
}

class InvalidExplicitTraitParameter
{
    use ExplicitTraitParameterRequirement;

    public function accept(self $value): void {}
}

function main() {}
