<?php
class A
{
    public function f(int $a, int $b): int
    {
        return $a + $b;
    }
}

class B extends A
{
    public function f(int ...$args): int
    {
        return 0;
    }
}

class C extends A
{
    public function f(int $a, int ...$rest): int
    {
        return $a;
    }
}

function main() {}
