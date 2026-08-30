--TEST--
Union type: return runtime type checking
--FILE--
<?php

function return_int_or_string($value): int|string {
    return $value;
}

function return_nullable_int($value): ?int {
    return $value;
}

function return_int_or_float($value): int|float {
    return $value;
}

function main() {
    // Valid returns - should pass
    var_dump(return_int_or_string(42));
    var_dump(return_int_or_string("hello"));
    var_dump(return_nullable_int(42));
    var_dump(return_nullable_int(null));
    var_dump(return_int_or_float(42));
    var_dump(return_int_or_float(3.14));

    // Invalid returns - should throw TypeError
    $errors = [];

    try {
        return_int_or_string(3.14);
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    try {
        return_int_or_string([]);
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    try {
        return_nullable_int("hello");
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    try {
        return_int_or_float("hello");
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    foreach ($errors as $err) {
        var_dump($err);
    }
}
?>
--EXPECT--
int(42)
string(5) "hello"
int(42)
NULL
int(42)
float(3.14)
string(76) "return_int_or_string(): Return value must be of type int|string, float given"
string(76) "return_int_or_string(): Return value must be of type int|string, array given"
string(70) "return_nullable_int(): Return value must be of type ?int, string given"
string(75) "return_int_or_float(): Return value must be of type int|float, string given"
