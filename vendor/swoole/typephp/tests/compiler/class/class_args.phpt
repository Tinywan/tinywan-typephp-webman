--TEST--
any
--FILE--
<?php
class TestX {
    public function run() {
        var_dump(__CLASS__);
        var_dump(__METHOD__);
    }
}
function test(TestX $o) {
    $o->run();
}
function main()
{
    $obj = new TestX();
    test($obj);
}
?>
--EXPECT--
string(5) "TestX"
string(10) "TestX::run"

