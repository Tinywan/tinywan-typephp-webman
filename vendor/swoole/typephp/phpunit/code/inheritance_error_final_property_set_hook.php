<?php

class FinalPropertySetHookParent
{
    public string $value {
        get => 'parent';
        final set {
        }
    }
}

class FinalPropertySetHookChild extends FinalPropertySetHookParent
{
    public string $value {
        set {
        }
    }
}
