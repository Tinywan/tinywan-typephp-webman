<?php

class VoidExpressionBinaryParent
{
    public function __construct()
    {
    }
}

class VoidExpressionBinaryChild extends VoidExpressionBinaryParent
{
    public function __construct()
    {
        $value = parent::__construct() + 1;
    }
}

function main(): void
{
    new VoidExpressionBinaryChild();
}
