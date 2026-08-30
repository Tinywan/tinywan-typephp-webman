--TEST--
ZE2 A final class cannot be inherited
--SKIPIF--
<?php die('skip'); ?>
--FILE--
<?php

final class base {
    function show() {
        echo "base\n";
    }
}

class derived extends base {
}

function main() {
    $t = new base();
    echo "Done\n"; // shouldn't be displayed
}
?>
--EXPECTF--
Fatal error: Class derived cannot extend final class base in %s on line %d
