<?php
class A
{
    public function &f(): array
    {
        static $a;
        $a ??= [];
        return $a;
    }
}

class B extends A
{
    public function f(): array
    {
        return [];
    }
}

function main() {}
