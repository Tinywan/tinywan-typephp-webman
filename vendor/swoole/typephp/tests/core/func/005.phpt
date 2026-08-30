--TEST--
Testing register_shutdown_function()
--SKIPIF--
<?php die("skip");?>
--FILE--
<?php

function foo()
{
    print "foo";
}
function main() {
    register_shutdown_function("foo");
    print "foo() will be called on shutdown...\n";
}
?>
--EXPECT--
foo() will be called on shutdown...
foo
