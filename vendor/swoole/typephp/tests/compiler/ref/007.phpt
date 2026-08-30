--TEST--
class const 001
--FILE--
<?php
class WorkerA
{
    protected function foo(string $name, string &$value) {
        $value = 'hello ' . $name;
    }

    public function baz(string $name) {
        $value = '';
        $this->foo($name, $value);
        return $value;
    }
}

class WorkerB extends WorkerA
{
    public function bar(string $name) {
        $value = '';
        $this->foo($name, $value);
        return $value;
    }
}

function main()
{
    $o = new WorkerB;
    var_dump($o->bar('php'));

    $o2 = new WorkerA;
    var_dump($o2->baz('swoole'));
}
?>
--EXPECT--
string(9) "hello php"
string(12) "hello swoole"