--TEST--
try catch
--FILE--
<?php
function inverse($x) {
    if (!$x) {
        throw new Exception('Division by zero.');
    }
    return number_format(1.0 / $x, 2);
}

function main() {
    try {
        echo inverse(5.0) . "\n";
        echo inverse(0) . "\n";
    } catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    } catch (RuntimeException $e) {
        echo 'Caught runtime exception: ',  $e->getMessage(), "\n";
    } finally {
        echo "Finally\n";
    }
}
?>
--EXPECT--
0.20
Caught exception: Division by zero.
Finally
