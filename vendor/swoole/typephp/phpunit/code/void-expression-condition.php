<?php

class VoidExpressionConditionParent
{
    public function __construct()
    {
    }
}

class VoidExpressionConditionChild extends VoidExpressionConditionParent
{
    public function __construct()
    {
        if (parent::__construct()) {
            echo "unreachable\n";
        }
    }
}

function main(): void
{
    new VoidExpressionConditionChild();
}
