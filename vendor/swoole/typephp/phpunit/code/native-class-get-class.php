<?php

#[Native]
class NativeGetClass
{
    public function name(): string
    {
        return get_class($this);
    }
}
