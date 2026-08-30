--TEST--
base_convert edge cases: invalid bases
--FILE--
<?php
try { var_dump(base_convert("10", 1, 10)); } catch (ValueError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
try { var_dump(base_convert("10", 10, 37)); } catch (ValueError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
var_dump(base_convert("10", 2, 10));
var_dump(base_convert("FF", 16, 10));
?>
--EXPECT--
ValueError: base_convert(): Argument #2 ($from_base) must be between 2 and 36 (inclusive)
ValueError: base_convert(): Argument #3 ($to_base) must be between 2 and 36 (inclusive)
string(1) "2"
string(3) "255"
