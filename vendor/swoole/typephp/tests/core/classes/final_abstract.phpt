--TEST--
ZE2 A final method cannot be abstract
--SKIPIF--
<?php die('skip, failed at compile time'); ?>
--FILE--
<?php

class fail {
    final abstract function show();
}
function main() {
    echo "Done\n"; // Shouldn't be displayed
}
?>
--EXPECTF--
Fatal error: Cannot use the final modifier on an abstract method in %s on line %d
