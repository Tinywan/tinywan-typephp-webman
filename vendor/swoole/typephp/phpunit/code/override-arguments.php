<?php

class OverrideArgumentsParent
{
    public function value(): void
    {
    }
}

class OverrideArgumentsChild extends OverrideArgumentsParent
{
    #[\Override(true)]
    public function value(): void
    {
    }
}
