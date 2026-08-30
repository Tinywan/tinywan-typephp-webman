--TEST--
ZE2 __get() signature check
--SKIPIF--
<?php die('skip, failed at compile time'); ?>
--FILE--
<?php
class Test {
    function __get($x,$y) {
    }
}
function main() {

}
?>
--EXPECTF--
Fatal error: Method Test::__get() must take exactly 1 argument in %s__set__get_002.php on line %d
