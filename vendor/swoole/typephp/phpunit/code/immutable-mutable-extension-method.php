<?php

class ImmutableExtensionTarget {}

#[MethodsFor(ImmutableExtensionTarget::class)]
class MutableExtensionMethods
{
    public static function touch(ImmutableExtensionTarget $value): void {}
}

function immutableMutableExtensionMethod(#[Immutable] ImmutableExtensionTarget $value): void
{
    $value->touch();
}
