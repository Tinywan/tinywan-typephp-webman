<?php

class Base
{
    protected function __construct(){}
}

class Other
{
    public static function make(): Base
    {
        return new Base();
    }
}

function main()
{
    Other::make();
}
