<?php

#[Native]
class NativeGetClassImplicit
{
    public function name(): string
    {
        return get_class();
    }
}
