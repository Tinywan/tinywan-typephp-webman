--TEST--
Type Declarations - Strict and weak typing modes
--FILE--
<?php
declare(strict_types=1);

// Test callable type
function apply_callable(callable $callback, int $value): int {
    return $callback($value);
}

// Test array type
function process_array(array $data): int {
    return count($data);
}

// Test object type
function get_class_name(object $obj): string {
    return get_class($obj);
}

// Test iterable type
function sum_iterable(iterable $numbers): int {
    $sum = 0;
    foreach ($numbers as $num) {
        $sum += $num;
    }
    return $sum;
}

class TestClass {}

function main() {
    // Test callable type
    var_dump(apply_callable(fn($x) => $x * 2, 5));
    var_dump(apply_callable('abs', -10));
    
    // Test array type
    var_dump(process_array([1, 2, 3]));
    var_dump(process_array([]));
    
    // Test object type
    $testObj = new TestClass();
    var_dump(get_class_name($testObj));
}
?>
--EXPECT--
int(10)
int(10)
int(3)
int(0)
string(9) "TestClass"
