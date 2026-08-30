<?php

trait NativeDynamicMagicTrait
{
    public function __serialize(): array
    {
        return [];
    }
}

#[Native]
class NativeTraitDynamicMagicMethod
{
    use NativeDynamicMagicTrait;
}
