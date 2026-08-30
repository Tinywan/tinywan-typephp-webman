--TEST--
try catch 2
--FILE--
<?php
function main() {
    try {
        throw new Exception('test error');
    } catch (Exception) {
        echo "Caught exception\n";
    }
}
?>
--EXPECT--
Caught exception
