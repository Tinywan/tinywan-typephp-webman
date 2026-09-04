<?php
class A
{
    const int|string X = 1;
    const mixed M = 1;
    const ?int N = null;
}

class B extends A
{
    // PHP 8.3 typed constants are covariant: narrowing is allowed.
    const int X = 2;
    const string M = 'a';
    const int N = 5;
}

function main() {}
