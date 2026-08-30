--TEST--
Closure parameters handle defaults and variadic arguments
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php
function main(): void
{
    $default = function ($value = 42) {
        var_dump($value);
    };
    $default();

    $variadic = function (...$values) {
        var_dump($values);
    };
    $variadic(1, "two", null);

    $required = function (?int $value) {
        var_dump($value);
    };
    try {
        $required();
    } catch (\Throwable $e) {
        var_dump(get_class($e));
        var_dump($e->getMessage());
    }

    $requiredWithDefault = function ($value, $default = 42) {
        var_dump($value, $default);
    };
    try {
        $requiredWithDefault();
    } catch (\Throwable $e) {
        var_dump(get_class($e));
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
int(42)
array(3) {
  [0]=>
  int(1)
  [1]=>
  string(3) "two"
  [2]=>
  NULL
}
string(18) "ArgumentCountError"
string(57) "stdClass::{closure}() expects exactly 1 argument, 0 given"
string(18) "ArgumentCountError"
string(58) "stdClass::{closure}() expects at least 1 argument, 0 given"
