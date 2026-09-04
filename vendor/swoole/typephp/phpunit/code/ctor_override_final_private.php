<?php
class A
{
    final private function __construct() {}
}

class B extends A
{
    public function __construct() {}
}

function main() {}
