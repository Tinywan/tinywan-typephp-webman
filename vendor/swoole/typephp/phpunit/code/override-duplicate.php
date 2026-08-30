<?php

class OverrideDuplicateParent
{
    public function value(): void
    {
    }
}

class OverrideDuplicateChild extends OverrideDuplicateParent
{
    #[\Override]
    #[\Override]
    public function value(): void
    {
    }
}
