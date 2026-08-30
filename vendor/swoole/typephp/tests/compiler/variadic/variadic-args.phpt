--TEST--
variadic args
--FILE--
<?php
function test($a, $b, int...$args)
{
    var_dump($a, $b, $args);
}

function main()
{
    test(1, 2, 3, 4, 5);
}
?>
--EXPECT--
int(1)
int(2)
array(3) {
  [0]=>
  int(3)
  [1]=>
  int(4)
  [2]=>
  int(5)
}

