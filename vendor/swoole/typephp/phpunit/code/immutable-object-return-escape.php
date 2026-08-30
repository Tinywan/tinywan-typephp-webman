<?php

class ImmutableReturnEscapeTarget {}

function immutableReturnEscape(#[Immutable] ImmutableReturnEscapeTarget $value): ImmutableReturnEscapeTarget
{
    return $value;
}
