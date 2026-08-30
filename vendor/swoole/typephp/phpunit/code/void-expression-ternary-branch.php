<?php

class VoidExpressionTernaryParent
{
    public function __construct()
    {
    }
}

class VoidExpressionTernaryChild extends VoidExpressionTernaryParent
{
    public function __construct()
    {
        $value = true ? parent::__construct() : 1;
    }
}

function main(): void
{
    new VoidExpressionTernaryChild();
}
