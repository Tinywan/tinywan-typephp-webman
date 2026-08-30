--TEST--
default array property
--FILE--
<?php
class Test {
    protected const VALUE = 'test';
    protected string $x = self::VALUE;

    public function bar() {
        var_dump($this->x);
    }
}

function main() {
    require __DIR__ . '/../../../src/Assert.php';
    $obj = new Test;
    $obj->bar();
}
?>
--EXPECT--
string(4) "test"