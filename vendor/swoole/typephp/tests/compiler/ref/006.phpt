--TEST--
ref
--FILE--
<?php
class Request {
    public function bar() {
        $this->foo('hello', $class);
        $this->dump($class);
    }

    public function foo($name, ?string &$class) {
        $class = __CLASS__;
    }

    public function dump(string $class) {
        var_dump($class);
    }
}

function main()
{
    $req = new Request;
    $req->bar();
}
?>
--EXPECT--
string(7) "Request"