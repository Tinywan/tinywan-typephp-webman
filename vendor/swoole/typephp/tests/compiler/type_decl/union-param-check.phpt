--TEST--
Union type: parameter runtime type checking
--FILE--
<?php

function expect_int_or_string(int|string $x): void {
    var_dump($x);
}

function expect_int_or_string_or_null(int|string|null $x): void {
    var_dump($x);
}

function expect_int_or_float(int|float $x): void {
    var_dump($x);
}

function expect_nullable_int(?int $x): void {
    var_dump($x);
}

function expect_nullable_string(?string $x): void {
    var_dump($x);
}

function expect_bool_or_array(bool|array $x): void {
    var_dump($x);
}

function main() {
    // Valid calls - should pass
    expect_int_or_string(42);
    expect_int_or_string("hello");
    expect_int_or_string_or_null(42);
    expect_int_or_string_or_null("hello");
    expect_int_or_string_or_null(null);
    expect_int_or_float(42);
    expect_int_or_float(3.14);
    expect_nullable_int(42);
    expect_nullable_int(null);
    expect_nullable_string("test");
    expect_nullable_string(null);
    expect_bool_or_array(true);
    expect_bool_or_array([1, 2, 3]);

    // Invalid calls - should throw TypeError
    $errors = [];

    try {
        expect_int_or_string(any(3.14));
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    try {
        expect_int_or_string(any([]));
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    try {
        expect_nullable_int(any("hello"));
    } catch (\TypeError $e) {
        $errors[] = $e->getMessage();
    }

    try {
        expect_bool_or_array(any(42));
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
string(5) "hello"
NULL
int(42)
float(3.14)
int(42)
NULL
string(4) "test"
NULL
bool(true)
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
string(80) "expect_int_or_string(): Argument #1 ($x) must be of type int|string, float given"
string(80) "expect_int_or_string(): Argument #1 ($x) must be of type int|string, array given"
string(74) "expect_nullable_int(): Argument #1 ($x) must be of type ?int, string given"
string(78) "expect_bool_or_array(): Argument #1 ($x) must be of type bool|array, int given"
