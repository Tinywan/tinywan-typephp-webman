--TEST--
'break' error (non positive integers)
--SKIPIF--
<?php die('skip'); ?>
--FILE--
<?php
function foo () {
    break 0;
}

function main() {
    foo();
}
?>
--EXPECTF--
Fatal error: 'break' operator accepts only positive integers in %sbreak_error_001.php on line 3
