--TEST--
compact
--FILE--
<?php
function main()
{
    preg_match('/(foo)(bar)(baz)/', 'foobarbaz', $matches, PREG_OFFSET_CAPTURE);
    var_dump($matches);
}
?>
--EXPECT--
array(4) {
  [0]=>
  array(2) {
    [0]=>
    string(9) "foobarbaz"
    [1]=>
    int(0)
  }
  [1]=>
  array(2) {
    [0]=>
    string(3) "foo"
    [1]=>
    int(0)
  }
  [2]=>
  array(2) {
    [0]=>
    string(3) "bar"
    [1]=>
    int(3)
  }
  [3]=>
  array(2) {
    [0]=>
    string(3) "baz"
    [1]=>
    int(6)
  }
}