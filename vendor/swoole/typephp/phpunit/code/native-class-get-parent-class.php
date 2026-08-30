<?php

#[Native]
class NativeGetParentBase {}

#[Native]
class NativeGetParentChild extends NativeGetParentBase
{
    public function parentName(): string|false
    {
        return get_parent_class($this);
    }
}
