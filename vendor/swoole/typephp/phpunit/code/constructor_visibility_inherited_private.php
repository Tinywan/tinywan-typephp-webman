<?php

class PrivateConstructorParent
{
    private function __construct()
    {
    }
}

class PrivateConstructorChild extends PrivateConstructorParent
{
}

function main()
{
    new PrivateConstructorChild();
}
