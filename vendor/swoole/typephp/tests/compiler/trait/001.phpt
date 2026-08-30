--TEST--
Single Trait with simple trait method
--FILE--
<?php
trait THello {
    protected string $name = 'world';
    static public int $count = 0;
    public const BAZ = 'baz';

    public function hello() {
        echo 'Hello ' . $this->name . PHP_EOL;
        $this->foo();
    }

    public function foo(): void {
        $array = [
            'count' => self::$count,
            'baz' => self::BAZ,
        ];
        var_dump($array);
    }
}

class TraitsTest {
    use THello;
}

function main() {
    $test = new TraitsTest();
    $test->hello();
}
?>
--EXPECT--
Hello world
array(2) {
  ["count"]=>
  int(0)
  ["baz"]=>
  string(3) "baz"
}
