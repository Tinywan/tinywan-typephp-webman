--TEST--
Native class: exit converts through the native string method
--FILE--
<?php

#[Native]
class NativeExitValue
{
    public function toString(): string
    {
        return 'native exit';
    }
}

function main(): void
{
    exit(new NativeExitValue());
}

?>
--EXPECT--
native exit
