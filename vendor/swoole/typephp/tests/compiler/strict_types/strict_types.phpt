--TEST--
TypePHP applies strict types without a declare directive
--FILE--
<?php

function add(int $a, int $b): int {
    return $a + $b;
}

function greet(string $name): string {
    return "Hello, " . $name;
}

function main(): void {
    var_dump(add(10, 20));
    var_dump(add(-5, 15));
    var_dump(greet("World"));
}
?>
--EXPECT--
int(30)
int(10)
string(12) "Hello, World"
