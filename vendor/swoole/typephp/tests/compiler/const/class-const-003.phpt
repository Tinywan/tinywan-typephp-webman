--TEST--
class const 002
--FILE--
<?php
class Worker
{
    public const int V1 = 1999;
    public const int V2 = self::V1;

    public const array ARR = [
        'a' => self::V1,
        'b' => self::V2,
    ];
}

function main()
{
    var_dump(Worker::V1);
    var_dump(Worker::V2);
    var_dump(Worker::ARR);
}
?>
--EXPECT--
int(1999)
int(1999)
array(2) {
  ["a"]=>
  int(1999)
  ["b"]=>
  int(1999)
}
