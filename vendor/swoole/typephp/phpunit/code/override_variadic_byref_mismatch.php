<?php
class A
{
    public function f(int &$a): void {}
}

class B extends A
{
    public function f(int ...$args): void {}
}

function main() {}
