<?php

#[Native]
class NativeInheritedMemberNameConflictBase
{
    public int $value = 0;
}

#[Native]
class NativeInheritedMemberNameConflictChild extends NativeInheritedMemberNameConflictBase
{
    public function value(): int
    {
        return 1;
    }
}
