<?php

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
class Immutable
{
    public function __construct()
    {
    }
}