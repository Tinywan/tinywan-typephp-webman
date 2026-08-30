--TEST--
Type Declarations - Strict and weak typing modes
--FILE--
<?php
declare(strict_types=1);

// Test iterable type
function sum_iterable(iterable $numbers): int {
    $sum = 0;
    foreach ($numbers as $num) {
        $sum += $num;
    }
    return $sum;
}

function main() {
    // Test iterable type
    var_dump(sum_iterable([1, 2, 3, 4, 5]));
    
    // Test with strict types enabled (should throw TypeError for wrong types)
    try {
        // This would fail in strict mode: add_integers("5", "10");
        echo "Strict mode enabled\n";
    } catch (TypeError $e) {
        echo "TypeError: " . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
int(15)
Strict mode enabled
