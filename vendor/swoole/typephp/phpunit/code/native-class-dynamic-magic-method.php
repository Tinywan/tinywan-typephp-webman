<?php

#[Native]
class NativeDynamicMagicMethod
{
    public function __get(string $name): mixed
    {
        return null;
    }
}
