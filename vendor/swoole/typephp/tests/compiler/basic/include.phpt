--TEST--
strlen
--FILE--
<?php
include __DIR__ . '/test_include.inc';
require __DIR__ . '/test_include.inc';
include_once __DIR__ . '/test_include.inc';
require_once __DIR__ . '/test_include.inc';
?>
--EXPECT--
test_include.inc
test_include.inc