--TEST--
closure 001
--FILE--
<?php
class Bar {
    public function __construct(callable $fn) {
        $data = $fn();
        var_dump($data);
    }
}

class Foo {
    private $arr = [1, 2, 3];
    function run() {
        $bar = new Bar(function () {
            return $this->arr;
        });
    }
}

function main()
{
    $foo = new Foo();
    $foo->run();
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
