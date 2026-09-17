<?php

#[Native]
class NativeInheritedPropertyNameConflictChild extends NativeInheritedPropertyNameConflictBase
{
    public int $value = 0;
}

#[Native]
class NativeInheritedPropertyNameConflictBase
{
    public function value(): int
    {
        return 1;
    }
}
