<?php
class A
{
    private function helper(): string
    {
        return 'A';
    }

    public function run(): string
    {
        // Binds A::helper() even on a B instance (private-scope binding).
        return $this->helper();
    }
}

class B extends A
{
    // Any signature is allowed: private methods are not inherited.
    public function helper(int $n = 0): int
    {
        return $n;
    }
}

class C
{
    final private function locked(): void {}
}

// Zend ignores FINAL on non-constructor private methods (declaring one only
// warns), so a child may still redeclare it.
class D extends C
{
    public function locked(): void {}
}

class E
{
    private static function make(): int
    {
        return 1;
    }
}

class F extends E
{
    public static function make(): string
    {
        return 'f';
    }
}

function main() {}
