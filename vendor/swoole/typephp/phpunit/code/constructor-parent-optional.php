<?php

class ConstructorOptionalParent
{
    public function __construct(string $label = 'parent')
    {
    }
}

class ConstructorOptionalChild extends ConstructorOptionalParent
{
    #[Constructor]
    private int $id;
}
