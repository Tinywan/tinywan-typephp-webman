<?php

trait OverrideTraitMissingMethod
{
    #[\Override]
    public function missing(): void
    {
    }
}

class OverrideTraitConsumer
{
    use OverrideTraitMissingMethod;
}
