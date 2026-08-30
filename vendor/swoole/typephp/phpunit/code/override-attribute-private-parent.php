<?php

class OverridePrivateParent
{
    private function value(): void
    {
    }
}

class OverridePrivateChild extends OverridePrivateParent
{
    #[\Override]
    public function value(): void
    {
    }
}
