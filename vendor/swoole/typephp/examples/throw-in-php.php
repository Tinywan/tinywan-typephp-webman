<?php
function inverse($x) {
    return number_format(1.0 / $x, 2);
}

function main() {
    try {
        echo inverse(5.0) . "\n";
        echo inverse(0) . "\n";
    } catch (DivisionByZeroError $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    } finally {
        echo "Finally\n";
    }
}
