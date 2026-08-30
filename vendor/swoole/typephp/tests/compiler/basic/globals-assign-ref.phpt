--TEST--
$GLOBALS array assignment and reference parameter update global slot
--FILE--
<?php

function globals_assign_ref_inc(&$value): void
{
    $value++;
}

function main(): void
{
    $GLOBALS['aot_globals_assign_ref_value'] = 40;
    $GLOBALS['aot_globals_assign_ref_value'] += 1;
    globals_assign_ref_inc($GLOBALS['aot_globals_assign_ref_value']);

    global $aot_globals_assign_ref_value;
    var_dump($aot_globals_assign_ref_value);
    var_dump($GLOBALS['aot_globals_assign_ref_value']);
}
?>
--EXPECT--
int(42)
int(42)
