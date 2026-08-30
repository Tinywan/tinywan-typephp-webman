--TEST--
'break' error (wrong level)
--SKIPIF--
<?php die('skip'); ?>
--FILE--
<?php
function foo () {
    while (1) {
        break 2;
    }
}
function main() {
    foo();
}
?>
--EXPECTF--
Fatal error: Cannot 'break' 2 levels in %sbreak_error_004.php on line 4
