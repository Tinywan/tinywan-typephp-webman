--TEST--
Nullable parameter without default is still required
--FILE--
<?php
function expect_nullable_int(?int $x): void
{
    var_dump($x);
}

function expect_nullable_with_default(?int $x, int $fallback = 1): void
{
    var_dump($x, $fallback);
}

function main(): void
{
    $fn = 'expect_nullable_int';
    try {
        $fn();
    } catch (\Throwable $e) {
        var_dump(get_class($e));
        var_dump($e->getMessage());
    }

    $fn = 'expect_nullable_with_default';
    try {
        $fn();
    } catch (\Throwable $e) {
        var_dump(get_class($e));
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(18) "ArgumentCountError"
string(57) "expect_nullable_int() expects exactly 1 argument, 0 given"
string(18) "ArgumentCountError"
string(67) "expect_nullable_with_default() expects at least 1 argument, 0 given"
