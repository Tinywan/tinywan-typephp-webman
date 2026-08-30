<?php

#[Native]
class NativeExplicitDestructor
{
    public function __destruct()
    {
    }
}

function main(): void
{
    $object = new NativeExplicitDestructor();
    $object->__destruct();
}
