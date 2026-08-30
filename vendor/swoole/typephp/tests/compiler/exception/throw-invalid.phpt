--TEST--
AOT invalid throw object matches PHP error
--FILE--
<?php
function throw_non_throwable_object(): void
{
    throw new stdClass();
}

function throw_mixed(mixed $value): void
{
    throw $value;
}

function main(): void
{
    try {
        throw_mixed(1);
    } catch (Error $e) {
        var_dump($e->getMessage());
    }

    try {
        throw_non_throwable_object();
    } catch (Error $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(22) "Can only throw objects"
string(52) "Cannot throw objects that do not implement Throwable"
