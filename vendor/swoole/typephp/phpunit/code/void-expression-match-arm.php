<?php

class VoidExpressionMatchParent
{
    public function __construct()
    {
    }
}

class VoidExpressionMatchChild extends VoidExpressionMatchParent
{
    public function __construct()
    {
        $value = match (1) {
            1 => parent::__construct(),
        };
    }
}

function main(): void
{
    new VoidExpressionMatchChild();
}
