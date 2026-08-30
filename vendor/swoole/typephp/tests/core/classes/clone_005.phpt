--TEST--
ZE2 object cloning, 5
--SKIPIF--
<?php die('skip, failed at compile time'); ?>
--FILE--
<?php
abstract class base {
  public $a = 'base';

  // disallow cloning once forever
  final protected function __clone() {}
}

class test extends base {
  // reenabling should fail
  public function __clone() {}
}

function main() {

}
?>
--EXPECTF--
Fatal error: Cannot override final method base::__clone() in %sclone_005.php on line 11
