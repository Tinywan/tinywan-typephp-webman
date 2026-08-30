--TEST--
Bug #65251: array_merge_recursive() recursion detection broken
--INI--
report_memleaks=0
--SKIPIF--
<?php //die("skip array_merge_recursive on \$GLOBALS triggers GC assertion failure"); ?>
--FILE--
<?php

/* Test that direct $GLOBALS read does not crash.
 * $GLOBALS is an INDIRECT to &EG(symbol_table).
 * Reading it directly must use SEPARATE_ARRAY (zend_array_dup)
 * to avoid manipulating refcount of the engine's symbol table. */
$array = $GLOBALS;
try {
    array_merge_recursive($array, $GLOBALS);
} catch (\Error $e) {
    echo $e->getMessage() . "\n";
}

echo "===DONE===\n";
?>
--EXPECT--
===DONE===
