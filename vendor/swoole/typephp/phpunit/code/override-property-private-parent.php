<?php

class OverridePropertyPrivateParent
{
    private string $value = 'private';
}

class OverridePropertyPrivateChild extends OverridePropertyPrivateParent
{
    #[\Override]
    public string $value = 'child';
}
