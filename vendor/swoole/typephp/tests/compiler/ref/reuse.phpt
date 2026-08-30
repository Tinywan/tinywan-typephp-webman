--TEST--
object link operator
--FILE--
<?php
function main()
{
    $arr = [1, 2, 3];
    foreach ($arr as &$value) {
        var_dump($value);
    }
    unset($value);

    foreach ($arr as &$value) {
        $value += 4;
    }
    unset($value);
    var_dump($arr);
}
?>
--EXPECT--
int(1)
int(2)
int(3)
array(3) {
  [0]=>
  int(5)
  [1]=>
  int(6)
  [2]=>
  int(7)
}