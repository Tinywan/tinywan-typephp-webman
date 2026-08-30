--TEST--
ZE2 A private constructor cannot be called
--SKIPIF--
<?php die('skip'); ?>
--FILE--
<?php

class Test
{
    function __construct()
    {
        echo __METHOD__ . "()\n";
    }
}

class Derived extends Test
{
    function __construct()
    {
        echo __METHOD__ . "()\n";
        parent::__construct();
    }

    static function f()
    {
        new Derived;
    }
}


class TestPriv
{
    private function __construct()
    {
        echo __METHOD__ . "()\n";
    }

    static function f()
    {
        new TestPriv;
    }
}


class DerivedPriv extends TestPriv
{
    function __construct()
    {
        echo __METHOD__ . "()\n";
        parent::__construct();
    }

    static function f()
    {
        new DerivedPriv;
    }
}

function main() {
    Derived::f();
    TestPriv::f();
    DerivedPriv::f();
}
?>
--EXPECTF--
Derived::__construct()
Test::__construct()
TestPriv::__construct()
DerivedPriv::__construct()

Fatal error: Uncaught Error: Cannot call private TestPriv::__construct() in %sctor_visibility.php:%d
Stack trace:
#0 %s(%d): DerivedPriv->__construct()
#1 %s(%d): DerivedPriv::f()
#2 {main}
  thrown in %sctor_visibility.php on line %d
