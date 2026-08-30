<?php

class ParentConstructorArgument
{
    public function __construct()
    {
    }
}

class ChildConstructorArgument extends ParentConstructorArgument
{
    public function __construct()
    {
        var_dump(parent::__construct());
    }
}

function main(): void
{
    new ChildConstructorArgument();
}
