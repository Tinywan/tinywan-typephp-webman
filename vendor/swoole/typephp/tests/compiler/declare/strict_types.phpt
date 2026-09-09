--TEST--
dynamic calls remain strict without a declare directive
--FILE--
<?php
function main() {
    $callable = 'strlen';
    try {
        $callable(123);
        echo "accepted\n";
    } catch (TypeError $error) {
        echo "TypeError\n";
    }
}
?>
--EXPECT--
TypeError
