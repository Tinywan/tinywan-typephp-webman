--TEST--
closure 001
--FILE--
<?php
function main()
{
    $a = 100;
    $b = [1, 2, 3];
    $fn = function ($x) use ($a, &$b) {
        var_dump($a);
        var_dump($b);
        var_dump($x);
        $b = [4, 5, 6];
    };
    $fn(1000);
    var_dump($b);
}
?>
--EXPECT--
int(100)
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
int(1000)
array(3) {
  [0]=>
  int(4)
  [1]=>
  int(5)
  [2]=>
  int(6)
}

