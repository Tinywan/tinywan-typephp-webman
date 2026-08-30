<?php

#[Native]
class NativeStaticSignature
{
    public function identity(): static
    {
        return $this;
    }
}
