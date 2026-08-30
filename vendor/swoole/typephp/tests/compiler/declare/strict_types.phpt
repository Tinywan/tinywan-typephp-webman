--TEST--
declare: strict types 1
--FILE--
<?php
declare(strict_types=1);
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
