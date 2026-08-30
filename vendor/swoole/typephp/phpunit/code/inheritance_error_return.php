<?php
class A
{
    function f(): int { return 1; }
}

class B extends A
{
    function f(): string { return ""; }
}

function main() {}
