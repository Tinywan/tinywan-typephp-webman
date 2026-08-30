--TEST--
main function
--FILE--
<?php
function foo() {
    var_dump(__FUNCTION__);
    return;
    var_dump(__FUNCTION__);
}
function main()
{
    foo();
}
?>
--EXPECT--
string(3) "foo"