<?php
class A
{
    public function __construct(int $x) {}
}

// A concrete parent constructor imposes no signature contract: the child may
// change the parameters and even narrow visibility.
class B extends A
{
    private function __construct(string $y, array $z)
    {
        parent::__construct(1);
    }
}

class C
{
    private function __construct(int $x) {}
}

// A private parent constructor may be redeclared freely.
class D extends C
{
    public function __construct(string $y) {}
}

abstract class E
{
    abstract public function __construct(int $x);
}

// Adding optional trailing parameters satisfies an abstract constructor.
class F extends E
{
    public function __construct(int $x, string $y = '') {}
}

function main() {}
