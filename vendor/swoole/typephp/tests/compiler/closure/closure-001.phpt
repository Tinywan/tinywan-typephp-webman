--TEST--
closure 001
--FILE--
<?php
function main()
{
    $a = 100;
    $b = [1, 2, 3];
    $fn = function ($x) use ($a, $b) {
        var_dump($a);
        var_dump($b);
        var_dump($x);
    };
    $fn(1000);
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

