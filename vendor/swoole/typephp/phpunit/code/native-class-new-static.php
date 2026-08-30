<?php

#[Native]
class NativeLateStaticConstruction
{
    public function duplicate(): NativeLateStaticConstruction
    {
        return new static();
    }
}

