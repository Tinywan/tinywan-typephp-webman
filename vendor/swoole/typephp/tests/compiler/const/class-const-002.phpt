--TEST--
class const 002
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
}

class Worker2 extends Worker {

}

function main()
{
    var_dump(Worker2::FOO);
    var_dump(Worker2::FOO[2]);
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