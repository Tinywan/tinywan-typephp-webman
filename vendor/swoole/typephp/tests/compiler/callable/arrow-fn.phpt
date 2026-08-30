--TEST--
First-Class Callable Syntax (PHP 8.1+)
--FILE--
<?php

// Test callable returning callable
function multiplier(int $factor): callable {
    return fn(int $value) => $value * $factor;
}

function main() {
    // Test callable returning callable
    $double = multiplier(2);
    $triple = multiplier(3);

    var_dump($double(5));
    var_dump($triple(5));
}
?>
--EXPECT--
int(10)
int(15)
