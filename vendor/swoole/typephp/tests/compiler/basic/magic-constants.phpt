--TEST--
Magic constants: __LINE__, __FILE__, __DIR__, __FUNCTION__, __METHOD__, __CLASS__
--FILE--
<?php

class TestMagicConst {
    public function testMethod() {
        echo "__METHOD__: "; var_dump(__METHOD__);
        echo "__CLASS__: "; var_dump(__CLASS__);
        echo "__FUNCTION__: "; var_dump(__FUNCTION__);
    }
}

function testFunction() {
    echo "__FUNCTION__ in function: "; var_dump(__FUNCTION__);
}

function main() {
    echo "__LINE__: "; var_dump(__LINE__);
    echo "__FILE__: "; var_dump(basename(__FILE__));
    echo "__DIR__: "; var_dump(basename(__DIR__));

    testFunction();

    $obj = new TestMagicConst();
    $obj->testMethod();
}

?>
--EXPECTF--
__LINE__: int(%d)
__FILE__: string(%d) "magic-constants.php"
__DIR__: string(%d) "basic"
__FUNCTION__ in function: string(%d) "testFunction"
__METHOD__: string(%d) "TestMagicConst::testMethod"
__CLASS__: string(%d) "TestMagicConst"
__FUNCTION__: string(%d) "testMethod"
