<?php
class A
{
    public function f(int ...$args): void {}
}

class B extends A
{
    public function f(int $a = 0): void {}
}

function main() {}
