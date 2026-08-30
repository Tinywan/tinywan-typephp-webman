<?php

#[Native]
class NativeGetCalledClass
{
    public function name(): string
    {
        return get_called_class();
    }
}
