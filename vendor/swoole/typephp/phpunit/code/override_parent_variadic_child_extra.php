<?php
class A
{
    public function f(int ...$args): void {}
}

class B extends A
{
    public function f(int $a = 1, int ...$rest): void {}
}

function main() {}
