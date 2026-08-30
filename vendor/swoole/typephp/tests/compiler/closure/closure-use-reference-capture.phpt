--TEST--
Closure use value and reference capture
--FILE--
<?php
function main(): void
{
    $arr = [1, 2];

    $copy = function () use ($arr) {
        $arr[] = 3;
        return $arr;
    };
    $copyResult = $copy();
    var_dump($arr);
    var_dump($copyResult);

    $ref = function () use (&$arr) {
        $arr[] = 4;
        return $arr;
    };
    $refResult = $ref();
    var_dump($arr);
    var_dump($refResult);

    $value = 'old';
    $returnCapturedRef = function () use (&$value) {
        return $value;
    };
    var_dump($returnCapturedRef());
    $value = 'new';
    var_dump($returnCapturedRef());

    $count = 0;
    $inc = function () use (&$count) {
        $count++;
        return $count;
    };
    var_dump($inc());
    var_dump($inc());
    var_dump($count);
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(4)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(4)
}
string(3) "old"
string(3) "new"
int(1)
int(2)
int(2)
