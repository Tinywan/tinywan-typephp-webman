--TEST--
Ensure class constants are not evaluated when a class is looked up to resolve inheritance during runtime.
--SKIPIF--
<?php die('skip, failed at compile time'); ?>
--FILE--
<?php
const K =  "nasty";
class C
{
  const X = E::A;
  public static $a = array(K => D::V, E::A => K);
}
class E extends D
{
  const A = "hello";
}
function main() {
  eval('class D extends C { const V = \'test\'; }');

  var_dump(C::X, C::$a, D::X, D::$a, E::X, E::$a);
}
?>
--EXPECT--
string(5) "hello"
array(2) {
  ["nasty"]=>
  string(4) "test"
  ["hello"]=>
  string(5) "nasty"
}
string(5) "hello"
array(2) {
  ["nasty"]=>
  string(4) "test"
  ["hello"]=>
  string(5) "nasty"
}
string(5) "hello"
array(2) {
  ["nasty"]=>
  string(4) "test"
  ["hello"]=>
  string(5) "nasty"
}
