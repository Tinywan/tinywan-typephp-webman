--TEST--
Error case: class constant as an encapsed containing a variable
--SKIPIF--
<?php die('skip, failed at compile time'); ?>
--FILE--
<?php
  class myclass
  {
      const myConst = "$myVar";
  }
function main() {

}
?>
--EXPECTF--
Fatal error: Constant expression contains invalid operations in %s on line %d
