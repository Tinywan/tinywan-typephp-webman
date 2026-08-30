<?php

class ConstructorRequiredParent
{
    public function __construct(string $label)
    {
    }
}

class ConstructorRequiredChild extends ConstructorRequiredParent
{
    #[Constructor]
    private int $id;
}
