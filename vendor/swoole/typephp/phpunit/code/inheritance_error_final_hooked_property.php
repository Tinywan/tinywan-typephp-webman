<?php

class FinalHookedPropertyParent
{
    final public string $value {
        get => 'parent';
    }
}

class FinalHookedPropertyChild extends FinalHookedPropertyParent
{
    public string $value {
        get => 'child';
    }
}
