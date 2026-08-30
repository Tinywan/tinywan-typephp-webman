--TEST--
$GLOBALS array element can be passed to reference parameter
--FILE--
<?php

function globals_ref_inc(&$value): void
{
    $value++;
}

function main(): void
{
    global $aot_globals_ref_value;
    $aot_globals_ref_value = 41;

    globals_ref_inc($GLOBALS['aot_globals_ref_value']);
    var_dump($aot_globals_ref_value);
}
?>
--EXPECT--
int(42)
