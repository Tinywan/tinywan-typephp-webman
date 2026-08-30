<?php

interface ParentByRef
{
    public function setValue(&$value): void;
}

interface ChildByRef extends ParentByRef
{
}

final class ByRefInterfaceReceiver implements ChildByRef
{
    public function setValue(&$value): void
    {
        $value = 42;
    }
}

function invokeByRefInterface(ChildByRef $receiver): void
{
    $receiver->setValue(value: $value);
    var_dump($value);
}

function main(): void
{
    invokeByRefInterface(new ByRefInterfaceReceiver());
}
