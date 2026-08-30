--TEST--
Constructor throws exception, destructor should NOT be called
--FILE--
<?php

class CtorThrowTest
{
    function __construct()
    {
        echo "CtorThrowTest::__construct\n";
        throw new Exception("fail in constructor");
    }

    function __destruct()
    {
        echo "CtorThrowTest::__destruct (SHOULD NOT APPEAR)\n";
    }
}

class NestedThrowTest
{
    function __construct()
    {
        echo "NestedThrowTest::__construct\n";
        $o = new CtorThrowTest;
    }

    function __destruct()
    {
        echo "NestedThrowTest::__destruct (SHOULD NOT APPEAR)\n";
    }
}

function main() {
    try {
        $a = new CtorThrowTest;
    } catch (Exception $e) {
        echo "Caught: " . $e->getMessage() . "\n";
    }

    try {
        $b = new NestedThrowTest;
    } catch (Exception $e) {
        echo "Caught: " . $e->getMessage() . "\n";
    }
}

?>
--EXPECT--
CtorThrowTest::__construct
Caught: fail in constructor
NestedThrowTest::__construct
CtorThrowTest::__construct
Caught: fail in constructor
