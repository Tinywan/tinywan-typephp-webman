--TEST--
count: TypeError for non-array/non-object
--FILE--
<?php
try { count("hello"); } catch (TypeError $e) { echo $e->getMessage() . "\n"; }
try { count(42); } catch (TypeError $e) { echo $e->getMessage() . "\n"; }
try { count(null); } catch (TypeError $e) { echo $e->getMessage() . "\n"; }
var_dump(count([1,2,3]));
?>
--EXPECT--
count(): Argument #1 ($value) must be of type Countable|array
count(): Argument #1 ($value) must be of type Countable|array
count(): Argument #1 ($value) must be of type Countable|array
int(3)
