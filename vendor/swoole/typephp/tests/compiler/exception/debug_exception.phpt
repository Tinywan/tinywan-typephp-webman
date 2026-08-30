--TEST--
debug exception propagation through nested function calls
--FILE--
<?php
function inner() {
    throw new Exception("error at inner level");
}
function middle() {
    inner();
}
function main() {
    try {
        middle();
    } catch (Exception $e) {
        echo "Caught: " . $e->getMessage() . "\n";
        echo "Exception class: " . get_class($e) . "\n";
    }
}
?>
--EXPECT--
Caught: error at inner level
Exception class: Exception
