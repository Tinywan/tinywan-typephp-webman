<?php

#[Native]
class NativeMemberNameConflict
{
    public int $value = 0;

    public function value(): int
    {
        return $this->value;
    }
}
