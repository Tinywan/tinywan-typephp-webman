<?php

class OverrideConstructorParent
{
    public function __construct()
    {
    }
}

class OverrideConstructorChild extends OverrideConstructorParent
{
    #[\Override]
    public function __construct()
    {
    }
}
