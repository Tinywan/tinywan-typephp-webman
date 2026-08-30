--TEST--
version_compare with operator parameter
--FILE--
<?php
var_dump(version_compare("1.2.3", "1.2.3"));
var_dump(version_compare("1.2.3", "1.2.4"));
var_dump(version_compare("1.2.3", "1.2.4", "<"));
var_dump(version_compare("1.2.3", "1.2.4", "<="));
var_dump(version_compare("1.2.3", "1.2.3", "=="));
var_dump(version_compare("1.2.3", "1.2.3", "!="));
try { version_compare("1.0", "2.0", "invalid"); } catch (ValueError $e) { echo $e->getMessage() . "\n"; }
?>
--EXPECT--
int(0)
int(-1)
bool(true)
bool(true)
bool(true)
bool(false)
version_compare(): Argument #3 ($operator) must be a valid comparison operator
