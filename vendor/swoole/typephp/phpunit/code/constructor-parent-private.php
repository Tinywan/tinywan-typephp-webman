<?php

class ConstructorPrivateParent
{
    private function __construct(string $label)
    {
    }
}

class ConstructorPrivateChild extends ConstructorPrivateParent
{
    #[Constructor]
    private int $id;
}
