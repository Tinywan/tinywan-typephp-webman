--TEST--
default array property
--FILE--
<?php

namespace App{
class Test {
    public const string TYPE_BOOL = 'php::Bool';
    public const string TYPE_INT = 'php::Int';
    public const string TYPE_FLOAT = 'php::Float';

    protected array $array1 = [
       'int' => self::TYPE_INT,
       'float' => self::TYPE_FLOAT,
       'bool' => self::TYPE_BOOL,
    ];

    protected $array2;

    function __construct() {
        $this->array2 = ['php', 'java',];
    }

    function bar() {
        var_dump($this->array1);
        var_dump($this->array2);
    }
}
}

namespace {
function main() {
    $obj = new App\Test;
    $obj->bar();
}
}
?>
--EXPECT--
array(3) {
  ["int"]=>
  string(8) "php::Int"
  ["float"]=>
  string(10) "php::Float"
  ["bool"]=>
  string(9) "php::Bool"
}
array(2) {
  [0]=>
  string(3) "php"
  [1]=>
  string(4) "java"
}