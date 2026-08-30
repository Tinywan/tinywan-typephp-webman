--TEST--
Known array statement writes preserve references, keys and expression results
--FILE--
<?php
declare(strict_types=1);

function main(): void
{
    $array = [1];
    $reference =& $array[0];

    $array[0] = 3;
    var_dump($reference);

    $array[] = 4;
    unset($array[1]);
    $array[] = 5;
    var_dump(array_keys($array), $array);

    $array[0] += 7;
    var_dump($reference);

    $assigned = ($array[3] = 9);
    $compound = ($array[0] += 1);
    var_dump($assigned, $compound);
}
?>
--EXPECT--
int(3)
array(2) {
  [0]=>
  int(0)
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  &int(3)
  [2]=>
  int(5)
}
int(10)
int(9)
int(11)
