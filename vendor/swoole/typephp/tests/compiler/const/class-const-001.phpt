--TEST--
class const 001
--FILE--
<?php
class Worker
{
    public const FOO = [
        'php',
        'java',
        'c++',
        'golang',
    ];
    public const BAR = "string constant";
    public const BAZ = 123;
}

function main()
{
    var_dump(Worker::FOO);
    var_dump(Worker::FOO[2]);
    var_dump(WOrker::BAR);
    var_dump(Worker::BAZ);
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(3) "php"
  [1]=>
  string(4) "java"
  [2]=>
  string(3) "c++"
  [3]=>
  string(6) "golang"
}
string(3) "c++"
string(15) "string constant"
int(123)