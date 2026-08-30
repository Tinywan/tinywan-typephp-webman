<?php

class InvalidConstructor
{
    #[Constructor]
    private int $value;

    public function __construct()
    {
    }
}
