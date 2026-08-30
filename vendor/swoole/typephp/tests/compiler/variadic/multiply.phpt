--TEST--
sum
--FILE--
<?php

// Test variadic with required parameters
function multiply($multiplier, ...$numbers): int {
    $result = 1;
    foreach ($numbers as $num) {
        $result *= $num;
    }
    return $result * $multiplier;
}

function main()
{
    // Test variadic with required parameters
    var_dump(multiply(2, 3, 4));      // 2 * 3 * 4 = 24
    var_dump(multiply(10, 5));         // 10 * 5 = 50
    var_dump(multiply(7));             // 7 (no additional numbers)
}
?>
--EXPECT--
int(24)
int(50)
int(7)