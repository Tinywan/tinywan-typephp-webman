<?php

#[Native]
class NativeReferenceAssignmentValue {}

function invalidNativeReferenceAssignment(): void
{
    $value = new NativeReferenceAssignmentValue();
    $alias =& $value;
}
