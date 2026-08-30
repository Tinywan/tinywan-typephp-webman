<?php

class FinalPropertyHookParent
{
    public string $value {
        final get => 'parent';
    }
}

class FinalPropertyHookChild extends FinalPropertyHookParent
{
    public string $value {
        get => 'child';
    }
}
