<?php

namespace ConstructorVisibility;

class Hidden
{
    private function __construct()
    {
    }
}

function main()
{
    new Hidden();
}
