<?php
class ImmutableObjectParameterTarget
{
    public function mutate(): void {}
}

function immutableObjectParameterCallsMutable(
    #[Immutable] ImmutableObjectParameterTarget $target,
): void {
    $target->mutate();
}
