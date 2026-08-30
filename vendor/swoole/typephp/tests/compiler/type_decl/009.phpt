--TEST--
Type Declarations
--FILE--
<?php
function foo($v1, $v2, $v3, object $v4): mixed {
    var_dump($v1, $v2, $v3, $v4);
}

function main() {
    foo(2026, "test", [2, 8], new stdClass());
}
?>
--EXPECTF--
int(2026)
string(4) "test"
array(2) {
  [0]=>
  int(2)
  [1]=>
  int(8)
}
object(stdClass)#%d (0) {
}