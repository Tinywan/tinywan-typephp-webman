<?php

class VoidExpressionArrayParent
{
    public function __construct()
    {
    }
}

class VoidExpressionArrayChild extends VoidExpressionArrayParent
{
    public function __construct()
    {
        $value = [parent::__construct()];
    }
}

function main(): void
{
    new VoidExpressionArrayChild();
}
