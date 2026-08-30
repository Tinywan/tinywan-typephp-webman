--TEST--
get_class_vars(): Testing the scope
--FILE--
<?php

class A {
    public $a = 1;
    protected $b = 2;
}

class B extends A {
    static public $aa = 4;
    static private $bb = 5;
    static protected $cc = 6;
}

class C extends B {
    public function __construct() {
        var_dump(get_class_vars('A'));
        var_dump(get_class_vars('B'));

        var_dump($this->a, $this->b);
    }
}

function main() {
    new C;
}
?>
--EXPECTF--
array(2) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
}
array(4) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
  ["aa"]=>
  int(4)
  ["cc"]=>
  int(6)
}
int(1)
int(2)
