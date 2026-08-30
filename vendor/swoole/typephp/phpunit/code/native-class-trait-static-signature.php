<?php

trait NativeTraitStaticSignatureProvider
{
    public function identity(): static
    {
        return $this;
    }
}

#[Native]
class NativeTraitStaticSignature
{
    use NativeTraitStaticSignatureProvider;
}
