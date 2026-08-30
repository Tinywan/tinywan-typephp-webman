--TEST--
preg_match output array can be reused through references
--FILE--
<?php

function main(): void
{
    preg_match('/(foo)(bar)/', 'foobar', $matches);

    $first =& $matches[1];
    $first = strtoupper($first);

    var_dump($matches);
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(6) "foobar"
  [1]=>
  &string(3) "FOO"
  [2]=>
  string(3) "bar"
}
