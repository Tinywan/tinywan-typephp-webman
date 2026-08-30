--TEST--
ZE2 interface with an unimplemented method
--SKIPIF--
<?php die('skip'); ?>
--FILE--
<?php

interface ThrowableInterface {
    public function getMessage();
    public function getErrno();
}

class Exception_foo implements ThrowableInterface {
    public $foo = "foo";

    public function getMessage() {
        return $this->foo;
    }
}

function main() {
    // this should die -- Exception class must be abstract...
    $foo = new Exception_foo;
    echo "Message: " . $foo->getMessage() . "\n";
}

?>
--EXPECTF--
Fatal error: Class Exception_foo contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (ThrowableInterface::getErrno) in %s on line %d
