<?php

#[Native]
class NativeWrongToAnyReturn
{
    public function toAny(): int
    {
        return 1;
    }
}
