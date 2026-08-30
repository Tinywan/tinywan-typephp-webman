--TEST--
Serialization of floats are correct
--INI--
--FILE--
<?php
ini_set('zend.assertions', '1');
try {
    assert(!is_float(0.0));
} catch (AssertionError $e) {
    echo 'assert(): ', $e->getMessage(), ' failed', PHP_EOL;
}
try {
    assert(!is_float(1.1));
} catch (AssertionError $e) {
    echo 'assert(): ', $e->getMessage(), ' failed', PHP_EOL;
}
try {
    assert(!is_float(1234.5678));
} catch (AssertionError $e) {
    echo 'assert(): ', $e->getMessage(), ' failed', PHP_EOL;
}
?>
--EXPECT--
assert():  failed
assert():  failed
assert():  failed