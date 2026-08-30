<?php
class ImmutableObjectPassedValue {}

function receiveMutableObject(ImmutableObjectPassedValue $value): void {}

function immutableObjectPassedToMutableParameter(
    #[Immutable] ImmutableObjectPassedValue $value,
): void {
    receiveMutableObject($value);
}
