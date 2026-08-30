<?php

interface NativeInterfaceAssignmentContract
{
    public function value(): int;
}

#[Native]
class NativeInterfaceAssignmentValue implements NativeInterfaceAssignmentContract
{
    public function value(): int
    {
        return 1;
    }
}

function replaceNativeInterfaceAssignment(
    NativeInterfaceAssignmentContract $target,
    NativeInterfaceAssignmentValue $value,
): void {
    $target = $value;
}
