<?php

interface InvalidMethodsForContract
{
}

#[MethodsFor(InvalidMethodsForContract::class)]
class InvalidInterfaceMethods
{
    public static function inspect(InvalidMethodsForContract $value): string
    {
        return 'invalid';
    }
}

function methods_for_interface_target(InvalidMethodsForContract $value): string
{
    return $value->inspect();
}
