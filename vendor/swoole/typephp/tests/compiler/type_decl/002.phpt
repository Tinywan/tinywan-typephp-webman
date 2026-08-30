--TEST--
Type Declarations - Strict and weak typing modes
--FILE--
<?php
declare(strict_types=1);

// Test scalar type declarations
function add_integers(int $a, int $b): int {
    return $a + $b;
}

function concatenate(string $a, string $b): string {
    return $a . $b;
}

function multiply_floats(float $a, float $b): float {
    return $a * $b;
}

function negate(bool $value): bool {
    return !$value;
}

// Test nullable types
function greet(?string $name): ?string {
    if ($name === null) {
        return null;
    }
    return "Hello, " . $name;
}

function main() {
    // Test integer types
    var_dump(add_integers(5, 10));
    var_dump(add_integers(-3, 7));
    
    // Test string types
    var_dump(concatenate("Hello, ", "World!"));
    var_dump(concatenate("", "Empty"));
    
    // Test float types
    var_dump(multiply_floats(2.5, 4.0));
    var_dump(multiply_floats(0.1, 0.2));
    
    // Test boolean types
    var_dump(negate(true));
    var_dump(negate(false));
    
    // Test nullable types
    var_dump(greet("Alice"));
    var_dump(greet(null));
}
?>
--EXPECT--
int(15)
int(4)
string(13) "Hello, World!"
string(5) "Empty"
float(10)
float(0.020000000000000004)
bool(false)
bool(true)
string(12) "Hello, Alice"
NULL