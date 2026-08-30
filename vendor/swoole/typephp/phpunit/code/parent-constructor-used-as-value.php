<?php

class ParentConstructorValue
{
    public function __construct()
    {
    }
}

class ChildConstructorValue extends ParentConstructorValue
{
    public function __construct()
    {
        $ret = parent::__construct();
    }
}

function main(): void
{
    new ChildConstructorValue();
}
