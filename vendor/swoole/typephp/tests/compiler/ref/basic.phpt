--TEST--
ref: basic
--FILE--
<?php
function main()
{
    require __DIR__ . '/../../../src/Assert.php';

    $a = [1, 2, 3];
    $b = &$a;
    $b[] = 5;

    $c = &$b;
    $c[] = 6;

    Assert::eq(count($a), 5);
    Assert::eq(count($b), 5);
    Assert::eq(count($c), 5);

    $array = [1, 2, 3];

    $ref = &$array;
    $ref[1] = 2026;

    $value = $ref;
    $value[2] = 1999;

    var_dump($array, $value);

    $value = 'done';
    var_dump($array, $value);
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2026)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2026)
  [2]=>
  int(1999)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2026)
  [2]=>
  int(3)
}
string(4) "done"
