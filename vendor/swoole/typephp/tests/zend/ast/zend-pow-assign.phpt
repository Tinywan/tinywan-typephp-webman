--TEST--
ZEND_POW_ASSIGN
--INI--
zend.assertions=1
--FILE--
<?php

try {
    $a = 0;
    assert(false && ($a **= 2));
} catch (AssertionError $e) {
    echo 'assert(): ', $e->getMessage(), ' failed', PHP_EOL;
}
?>
--EXPECT--
assert():  failed
