<?php
class A
{
    final public function __construct() {}
}

class B extends A
{
    public function __construct() {}
}

function main() {}
