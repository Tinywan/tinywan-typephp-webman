--TEST--
multiple default array properties
--FILE--
<?php

class Test
{
    public array $literalStrings = [];
    public array $classMap = [];
    public array $stdTypeMap = [];
    public array $funcMap = [];
    public array $propMap = [];
}

function main(): void
{
    $obj = new Test;
    var_dump(get_object_vars($obj));
}
?>
--EXPECT--
array(5) {
  ["literalStrings"]=>
  array(0) {
  }
  ["classMap"]=>
  array(0) {
  }
  ["stdTypeMap"]=>
  array(0) {
  }
  ["funcMap"]=>
  array(0) {
  }
  ["propMap"]=>
  array(0) {
  }
}
