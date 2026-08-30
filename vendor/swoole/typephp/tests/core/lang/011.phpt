--TEST--
Testing nested functions
--FILE--
<?php
function F()
{
    $a = "Hello ";
    return($a);
}

function G()
{
  static $myvar = 4;

  echo "$myvar ";
  echo F();
  echo "$myvar";
}

function main() {
    G();
}
?>
--EXPECT--
4 Hello 4
