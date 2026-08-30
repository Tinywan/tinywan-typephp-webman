<?php

class ImmutableOverrideValue {}

class ImmutableParameterParent
{
    public function inspect(#[Immutable] ImmutableOverrideValue $value): void {}
}

class ImmutableParameterChild extends ImmutableParameterParent
{
    public function inspect(ImmutableOverrideValue $value): void {}
}
