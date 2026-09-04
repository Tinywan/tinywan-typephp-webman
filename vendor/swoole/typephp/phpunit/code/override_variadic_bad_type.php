<?php
class A
{
    public function f(int $a, string $b): void {}
}

class B extends A
{
    public function f(int ...$args): void {}
}

function main() {}
