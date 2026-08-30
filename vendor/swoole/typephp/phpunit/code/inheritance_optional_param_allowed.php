<?php
class A
{
    public function f($x) {}
}

class B extends A
{
    public function f($x, $y = 1) {}
}

function main() {}
