--TEST--
Single Trait with simple trait method
--FILE--
<?php
trait THello {
    protected const array CONST_ARRAY = [
        'test_fn_1' => ['toInt' => 123,],
        'test_fn_2' => ['toInt' => 234, ],
        'test_fn_3' => ['toInt' => 333,],
    ];

    public function run(string $key1) {
        var_dump(isset(self::CONST_ARRAY[$key1]['toInt']));
        var_dump(self::CONST_ARRAY[$key1]);
    }
}

class TraitsTest {
    use THello;

    function hello1() {
        $this->run('test_fn_2');
    }
}

class Test extends TraitsTest {
    function hello2() {
        $this->run('test_fn_2');
    }
}

function main() {
    $o1 = new TraitsTest();
    $o1->hello1();
    $o2 = new Test();
    $o2->hello2();
}
?>
--EXPECT--
bool(true)
array(1) {
  ["toInt"]=>
  int(234)
}
bool(true)
array(1) {
  ["toInt"]=>
  int(234)
}