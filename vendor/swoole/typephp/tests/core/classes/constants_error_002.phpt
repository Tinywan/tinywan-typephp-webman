--TEST--
Error case: class constant as an array
--FILE--
<?php
  class myclass
  {
      const myConst = array();
  }
function main() {

}
?>
--EXPECT--
