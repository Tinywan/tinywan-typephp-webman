--TEST--
finally side effects run before return and throw leave the frame
--FILE--
<?php

class FinallySideEffectException extends Exception {}

function finally_return_case(): string
{
    try {
        echo "try-return\n";
        return "returned";
    } finally {
        echo "finally-return\n";
    }
}

function finally_throw_case(): void
{
    try {
        echo "try-throw\n";
        throw new FinallySideEffectException("thrown");
    } finally {
        echo "finally-throw\n";
    }
}

function main(): void
{
    var_dump(finally_return_case());

    try {
        finally_throw_case();
    } catch (FinallySideEffectException $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
try-return
finally-return
string(8) "returned"
try-throw
finally-throw
string(6) "thrown"
