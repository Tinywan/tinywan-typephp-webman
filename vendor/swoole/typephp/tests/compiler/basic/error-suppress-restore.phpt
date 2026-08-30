--TEST--
Error suppression should restore error_reporting after expression evaluation
--FILE--
<?php

function suppressed_reporting_level(): int
{
    return error_reporting();
}

function main(): void
{
    $before = error_reporting();
    $inside = @suppressed_reporting_level();
    $after = error_reporting();

    var_dump($inside === $before);
    var_dump($after === $before);
}
?>
--EXPECT--
bool(false)
bool(true)
