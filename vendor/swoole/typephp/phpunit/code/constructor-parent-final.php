<?php

class ConstructorFinalParent
{
    final protected function __construct()
    {
    }
}

class ConstructorFinalChild extends ConstructorFinalParent
{
    #[Constructor]
    private int $id;
}
