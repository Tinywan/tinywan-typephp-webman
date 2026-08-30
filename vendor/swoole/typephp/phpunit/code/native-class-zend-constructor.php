<?php

#[Native]
class NativeConstructorArgument
{
    public int $value = 1;
}

class ZendBackedConstructor
{
    public function __construct(NativeConstructorArgument $native)
    {
    }
}
