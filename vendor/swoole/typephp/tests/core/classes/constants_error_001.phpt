--TEST--
Error case: duplicate class constant definition
--SKIPIF--
<?php die('skip, failed at compile time'); ?>
--FILE--
<?php
  class myclass
  {
      const myConst = "hello";
      const myConst = "hello again";
  }

function main() {

}
?>
--EXPECTF--
Fatal error: Cannot redefine class constant myclass::myConst in %s on line 5
