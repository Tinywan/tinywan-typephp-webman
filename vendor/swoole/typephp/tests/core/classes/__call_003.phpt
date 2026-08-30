--TEST--
Force pass-by-reference to __call (Incompatible)
--FILE--
<?php
  class C
  {
      function __call($name, $values)
      {
          $values[0][0] = 'changed';
      }
  }
function main() {
  $a = array('original');
  $b = array('original');

  $c = new C;
  $c->f($a);
  $c->f($b);

  var_dump($a, $b);
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(8) "original"
}
array(1) {
  [0]=>
  string(8) "original"
}