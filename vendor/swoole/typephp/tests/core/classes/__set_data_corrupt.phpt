--TEST--
ZE2 Data corruption in __set
--FILE--
<?php
class foo {
        const foobar=1;
        public $pp = array('t'=>null);

        function bar() {
               $this->t = 'f';
               echo $this->t;
        }
        function __get($prop)
        {
                return $this->pp[$prop];
        }
        function __set($prop, $val)
        {
                echo "__set";
                $this->pp[$prop] = '__test';
        }
}

function main() {
    $f = new foo;
    $f->bar();
}
?>
--EXPECT--
__set__test
