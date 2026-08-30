--TEST--
Boolean literals on either side of strict comparisons
--FILE--
<?php

function compare_bool_literal(bool $pjax): void
{
    var_dump(true === $pjax);
    var_dump($pjax === true);
    var_dump(false === $pjax);
    var_dump($pjax === false);
    var_dump(true !== $pjax);
    var_dump($pjax !== true);
}

function main(): void
{
    compare_bool_literal(true);
    compare_bool_literal(false);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
