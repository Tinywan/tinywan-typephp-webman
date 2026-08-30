--TEST--
Closure composite type declarations use runtime type checks
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

interface ClosureIA {}
interface ClosureIB {}
class ClosureBoth implements ClosureIA, ClosureIB {}
class ClosureOnlyA implements ClosureIA {}

function main(): void
{
    $nullable = function (?int $value) {
        var_dump($value);
    };
    $nullable(null);

    $union = function (int|string $union) {
        var_dump($union);
    };
    $union("ok");

    $variadic = function (int|string ...$values) {
        var_dump($values);
    };
    $variadic(1, "two");

    $intersection = function (ClosureIA&ClosureIB $value) {
        var_dump(get_class($value));
    };
    $intersection(new ClosureBoth());

    $returnUnion = function ($value): int|string {
        return $value;
    };
    var_dump($returnUnion(42));

    try {
        $nullable("bad");
    } catch (\TypeError $e) {
        echo $e->getMessage(), "\n";
    }
    try {
        $union([]);
    } catch (\TypeError $e) {
        echo $e->getMessage(), "\n";
    }
    try {
        $variadic(1, []);
    } catch (\TypeError $e) {
        echo $e->getMessage(), "\n";
    }
    try {
        $intersection(new ClosureOnlyA());
    } catch (\TypeError $e) {
        echo $e->getMessage(), "\n";
    }
    try {
        $returnUnion([]);
    } catch (\TypeError $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
NULL
string(2) "ok"
array(2) {
  [0]=>
  int(1)
  [1]=>
  string(3) "two"
}
string(11) "ClosureBoth"
int(42)
{closure}(): Argument #1 ($value) must be of type ?int, string given
{closure}(): Argument #1 ($union) must be of type int|string, array given
{closure}(): Argument #2 ($values) must be of type int|string, array given
{closure}(): Argument #1 ($value) must be of type ClosureIA&ClosureIB, object given
{closure}(): Return value must be of type int|string, array given
