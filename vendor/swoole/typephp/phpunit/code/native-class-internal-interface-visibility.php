<?php

#[Native]
class NativeProtectedCountable implements Countable
{
    protected function count(): int
    {
        return 1;
    }
}
